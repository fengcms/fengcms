#!/usr/bin/env python3
"""Convert FengCMS legacy encrypted model export to a declarative package.

This tool never connects to a database or executes the decrypted SQL.
"""

import argparse
import base64
import hashlib
import json
import re
import sys
from pathlib import Path


MODULE_KEYS = ('name', 'item', 'unit', 'description', 'recover', 'search', 'status')
FIELD_KEYS = ('sort', 'name', 'aliases', 'type', 'defaults', 'must', 'length',
              'enable', 'search', 'nullmsg', 'errormsg', 'status')
FIELD_TYPES = {
    1: (0, 'title', 'title.html'), 2: (0, 'classid', 'classify.html'),
    3: (0, 'attrib', 'attrib.html'), 4: (0, 'tags', 'tags.html'),
    5: (0, 'author', 'author.html'), 6: (0, 'inputer', 'inputer.html'),
    7: (0, 'origin', 'origin.html'), 8: (0, 'info', 'info.html'),
    9: (0, 'content', 'content.html'), 10: (0, 'img', 'img.html'),
    11: (0, 'template', 'template.html'), 12: (0, 'date', 'date.html'),
    13: (0, 'time', 'time.html'), 14: (0, 'hits', 'hits.html'),
    15: (0, 'hidden', 'status.html'), 16: (0, 'hidden', 'hidden.html'),
    17: (1, 'text', 'text_w.html'), 18: (1, 'text', 'text_n.html'),
    19: (1, 'text', 'text_s.html'), 20: (1, 'text', 'text_z.html'),
    21: (1, 'text', 'text_p.html'), 22: (1, 'text', 'text_m.html'),
    23: (1, 'text', 'text_e.html'), 24: (1, 'text', 'text_url.html'),
    25: (1, 'textarea', 'textarea.html'), 26: (1, 'htmledit', 'htmledit.html'),
    27: (1, 'hidden', 'hidden.html'), 28: (1, 'pic', 'pic.html'),
    29: (1, 'batchpic', 'batchpic.html'), 30: (1, 'upfile', 'upfile.html'),
}
IDENT = re.compile(r'[A-Za-z_][A-Za-z0-9_]*\Z')
PROJECT = re.compile(r'[A-Za-z][A-Za-z0-9_]{0,29}\Z')
TYPE = re.compile(r'(?:tinyint|smallint|mediumint|int|bigint)(?:\([1-9][0-9]{0,2}\))?(?: unsigned)?|(?:varchar|char)\([1-9][0-9]{0,3}\)|(?:text|mediumtext|longtext|date|datetime|timestamp)|decimal\([1-9][0-9]?,[0-9]{1,2}\)', re.I)


def fail(message):
    raise ValueError(message)


def decrypt(ciphertext):
    key = hashlib.md5(b'123456789').hexdigest().encode('ascii')
    try:
        raw = base64.b64decode(ciphertext + '=' * (-len(ciphertext) % 4), validate=True)
    except ValueError as exc:
        raise ValueError('旧导入码不是有效的 Base64。') from exc
    box = list(range(256))
    j = 0
    for i in range(256):
        j = (j + box[i] + key[i % len(key)]) % 256
        box[i], box[j] = box[j], box[i]
    a = j = 0
    output = bytearray()
    for value in raw:
        a = (a + 1) % 256
        j = (j + box[a]) % 256
        box[a], box[j] = box[j], box[a]
        output.append(value ^ box[(box[a] + box[j]) % 256])
    if len(output) < 8 or output[:8] != hashlib.md5(bytes(output[8:]) + key).hexdigest()[:8].encode():
        fail('旧导入码校验失败。')
    try:
        return output[8:].decode('utf-8')
    except UnicodeDecodeError as exc:
        raise ValueError('旧导入码不是 UTF-8；请先核对原站字符集。') from exc


def split_sql(source, delimiter):
    pieces = []
    start = 0
    quoted = False
    escaped = False
    for index, char in enumerate(source):
        if escaped:
            escaped = False
        elif quoted and char == '\\':
            escaped = True
        elif char == "'":
            quoted = not quoted
        elif char == delimiter and not quoted:
            pieces.append(source[start:index].strip())
            start = index + 1
    if quoted or escaped:
        fail('旧 SQL 的引号不完整。')
    tail = source[start:].strip()
    if tail:
        fail('旧 SQL 存在未结束的语句。') if delimiter == ';' else pieces.append(tail)
    return [piece for piece in pieces if piece]


def sql_string(token, legacy_null=True):
    token = token.strip()
    if token.upper() == 'NULL':
        return None
    if len(token) < 2 or token[0] != "'" or token[-1] != "'":
        fail('旧记录包含非字符串值或表达式。')
    data = token[1:-1]
    result = []
    i = 0
    while i < len(data):
        char = data[i]
        if char == '\\':
            i += 1
            if i >= len(data) or data[i] not in "\\'\"0":
                fail('旧记录包含不支持的字符串转义。')
            char = '\0' if data[i] == '0' else data[i]
        elif char == "'":
            fail('旧记录包含未转义的引号。')
        result.append(char)
        i += 1
    value = ''.join(result)
    return value or None if legacy_null else value  # Legacy importer rewrote every '' to NULL.


def parse_insert(statement, table):
    match = re.fullmatch(r'INSERT\s+INTO\s+`#_' + table + r'`\s*\(([^)]*)\)\s*VALUES\s*\((.*)\)', statement, re.I | re.S)
    if not match:
        fail('旧导入码出现不允许的 SQL 语句。')
    columns = [part.strip() for part in match[1].split(',')]
    if not all(re.fullmatch(r'`[A-Za-z_][A-Za-z0-9_]*`', part) for part in columns):
        fail('旧记录的列名不正确。')
    names = [part[1:-1] for part in columns]
    values = [sql_string(part) for part in split_sql(match[2] + ',', ',')]
    if len(names) != len(values) or len(set(names)) != len(names):
        fail('旧记录的列与值数量不一致。')
    return dict(zip(names, values))


def parse_ddl(statement):
    match = re.fullmatch(r'CREATE\s+TABLE\s+`#_#project`\s*\((.*)\)\s*ENGINE\s*=\s*(?:MyISAM|InnoDB)\b(.*)', statement, re.I | re.S)
    if not match:
        fail('旧模型建表语句不符合旧导出格式。')
    options = match[2].strip()
    while options:
        found = re.match(r'(?:AUTO_INCREMENT|DEFAULT\s+CHARSET|CHARSET|COLLATE|ROW_FORMAT)\s*=\s*[A-Za-z0-9_]+\b', options, re.I)
        if not found:
            found = re.match(r"COMMENT\s*=\s*'(?:\\.|[^'\\])*'", options, re.I | re.S)
        if not found:
            fail('旧模型包含无法表达的建表选项。')
        options = options[found.end():].strip()
    definitions = split_sql(match[1] + ',', ',')
    columns = []
    primary = 0
    for definition in definitions:
        if re.fullmatch(r'PRIMARY\s+KEY\s*\(`id`\)', definition, re.I):
            primary += 1
            continue
        col = re.fullmatch(r'`([A-Za-z_][A-Za-z0-9_]*)`\s+(.+)', definition, re.S)
        if not col:
            fail('旧模型包含不支持的索引或数据库字段。')
        type_match = TYPE.match(col[2])
        if not type_match:
            fail('旧模型包含新格式不支持的字段类型。')
        sql_type = type_match[0].lower()
        if sql_type.startswith(('varchar(', 'char(')) and int(sql_type.split('(')[1][:-1]) > 1000:
            fail('旧模型字段长度超过新格式上限。')
        rest = col[2][type_match.end():]
        nullable = True
        default = None
        auto = False
        while rest.strip():
            rest = rest.lstrip()
            if found := re.match(r'NOT\s+NULL\b', rest, re.I):
                nullable = False
            elif found := re.match(r'NULL\b', rest, re.I):
                nullable = True
            elif found := re.match(r'DEFAULT\s+(NULL|-?[0-9]+|\'(?:\\.|[^\'\\])*\')', rest, re.I | re.S):
                default = sql_string(found[1], False) if found[1].startswith("'") else (None if found[1].upper() == 'NULL' else found[1])
            elif found := re.match(r'AUTO_INCREMENT\b', rest, re.I):
                auto = True
            elif found := re.match(r'COMMENT\s+\'(?:\\.|[^\'\\])*\'', rest, re.I | re.S):
                pass
            else:
                fail('旧字段包含无法安全表达的约束或默认值。')
            rest = rest[found.end():]
        if auto != (col[1] == 'id'):
            fail('旧模型主键不是预期的自增 id。')
        columns.append({'name': col[1], 'type': sql_type, 'nullable': nullable, 'default': default})
    if primary != 1:
        fail('旧模型主键或索引不符合新格式。')
    return columns


def validate_package(module, columns, fields):
    for key, limit in [('name', 50), ('item', 50), ('unit', 10), ('description', 255)]:
        if module[key] is not None and len(module[key]) > limit:
            fail('旧模型属性超过新格式长度上限。')
    if any(module[key] not in ('0', '1') for key in ('recover', 'search', 'status')):
        fail('旧模型开关值不正确。')
    if not 2 <= len(columns) <= 100 or len(fields) > 100:
        fail('旧模型字段数量超出新格式范围。')
    names = [column['name'] for column in columns]
    if (len(names) != len(set(names)) or columns[0]['name'] != 'id' or
            not columns[0]['type'].startswith('int') or columns[0]['nullable'] or
            not {'title', 'info', 'content', 'img', 'status', 'time', 'html'} <= set(names)):
        fail('旧模型缺少必要的系统字段。')
    field_names = set()
    for field in fields:
        name = field['name']
        if (name is None or not IDENT.fullmatch(name) or len(name) > 50 or
                name in field_names or
                name not in names and not (name == 'attrib' and
                    {'attrib_j', 'attrib_g', 'attrib_t', 'attrib_r', 'attrib_d', 'attrib_h'} <= set(names))):
            fail('旧后台字段与数据表不匹配。')
        field_names.add(name)
        for key in ('sort', 'must', 'enable', 'search', 'status'):
            value = field[key]
            if value is None or not value.isdecimal() or int(value) > 100000:
                fail('旧字段的开关或排序值不正确。')
        for key in ('defaults', 'length', 'nullmsg', 'errormsg'):
            if field[key] is not None and len(field[key].encode('utf-8')) > 255:
                fail('旧字段配置超过新格式长度上限。')
    if not set(names) <= {name for name in names if IDENT.fullmatch(name) and len(name) <= 50}:
        fail('旧模型列名超出新格式范围。')
    for column in columns:
        if column['default'] is not None and len(column['default'].encode('utf-8')) > 255:
            fail('旧字段默认值超过新格式上限。')


def convert(ciphertext, project):
    if not PROJECT.fullmatch(project):
        fail('请提供有效的原模型项目代号。')
    source = decrypt(ciphertext)
    if len(source.encode()) > 1048576:
        fail('旧模型超过 1 MB 上限。')
    statements = split_sql(source, ';')
    if len(statements) < 2:
        fail('旧导入码缺少模型结构或记录。')
    columns = parse_ddl(statements[0])
    module = parse_insert(statements[1], 'module')
    if (set(module) != set(MODULE_KEYS) | {'id', 'project', 'type', 'time'} or
            module.get('id') is not None or module.get('project') != '#project' or
            module.get('type') != '1' or
            any('#project' in value for key, value in module.items() if key != 'project' and isinstance(value, str))):
        fail('旧模型记录不完整，或并非自定义模型。')
    fields = []
    for statement in statements[2:]:
        field = parse_insert(statement, 'module_field')
        if set(field) != set(FIELD_KEYS) | {'id', 'module_id', 'time'} or field.get('id') is not None or field.get('module_id') != '0':
            fail('旧字段记录不完整。')
        fields.append({key: field[key] for key in FIELD_KEYS})
    if not fields:
        fail('旧模型没有后台字段配置。')
    types = []
    for value in sorted({field['type'] for field in fields}, key=lambda x: int(x or -1)):
        if value is None or not value.isdecimal() or int(value) not in FIELD_TYPES:
            fail('旧模型引用未知字段类型；请从原站重新导出新格式，或人工整理。')
        f, cls, quote = FIELD_TYPES[int(value)]
        types.append({'id': int(value), 'f': f, 'class': cls, 'quote': quote})
    validate_package(module, columns, fields)
    result = {'format': 'fengcms-model-v1', 'source_project': project,
              'module': {key: module[key] for key in MODULE_KEYS},
              'columns': columns, 'field_types': types, 'fields': fields}
    encoded = json.dumps(result, ensure_ascii=False, indent=2).encode('utf-8')
    if len(encoded) > 1048576:
        fail('转换结果超过 1 MB 上限。')
    return encoded + b'\n'


def main():
    parser = argparse.ArgumentParser(description='离线转换 FengCMS 旧模型导入码，不执行 SQL')
    parser.add_argument('--input', required=True, type=Path, help='只含旧加密导入码的 UTF-8 文件')
    parser.add_argument('--output', required=True, type=Path, help='新 .fengmodel.json 路径，必须不存在')
    parser.add_argument('--source-project', required=True, help='原模型项目代号（旧码中已替换为占位符）')
    args = parser.parse_args()
    try:
        if args.input.is_symlink() or not args.input.is_file() or args.input.stat().st_size > 1500000:
            fail('输入文件不存在、是符号链接或超过上限。')
        if args.output.exists() or args.output.is_symlink() or args.output.parent.is_symlink():
            fail('输出文件已存在或路径包含符号链接。')
        data = args.input.read_text(encoding='utf-8').strip()
        output = convert(data, args.source_project)
        with args.output.open('xb') as stream:
            stream.write(output)
        args.output.chmod(0o600)
        print(f'转换完成：{args.output}；字段 {len(json.loads(output)["fields"])} 个。请先检查 JSON 预览，再到后台导入。')
    except (OSError, UnicodeError, ValueError) as exc:
        print(f'转换失败：{exc}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
