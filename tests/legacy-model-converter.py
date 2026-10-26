#!/usr/bin/env python3
"""Exercise the offline converter with an old-exporter-shaped encrypted code."""

import base64
import hashlib
import importlib.util
import re
from pathlib import Path


spec = importlib.util.spec_from_file_location('legacy_model', Path(__file__).resolve().parents[1] / 'tools/convert-legacy-model.py')
converter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(converter)
seed = (Path(__file__).resolve().parents[1] / 'install/install.sql').read_text()
seed_types = {int(i): (int(f), cls, quote) for i, f, cls, quote in
              re.findall(r"\((\d+),([01]),'[^']*','([^']*)',(?:NULL|'[^']*'),'([^']*)'\)", seed)}
assert converter.FIELD_TYPES == seed_types, 'legacy field type map differs from installation seed'


def encrypt_old(source):
    key = hashlib.md5(b'123456789').hexdigest().encode()
    raw = hashlib.md5(source.encode() + key).hexdigest()[:8].encode() + source.encode()
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
    return base64.b64encode(output).decode().rstrip('=')


ddl = '''CREATE TABLE `#_#project` (
  `id` int(11) NOT NULL auto_increment,
  `title` varchar(255) DEFAULT NULL,
  `info` text,
  `content` longtext,
  `img` text,
  `status` int(1) DEFAULT '1',
  `time` int(11) DEFAULT NULL,
  `html` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='商品';'''
module = "insert into `#_module`(`id`,`name`,`project`,`type`,`item`,`unit`,`description`,`recover`,`search`,`status`,`time`) values('','商品','#project','1','商品','件','描述','1','1','1','123');"
field = "insert into `#_module_field`(`id`,`module_id`,`sort`,`name`,`aliases`,`type`,`defaults`,`must`,`length`,`enable`,`search`,`nullmsg`,`errormsg`,`time`,`status`) values('','0','1','title','标题','1','','1','0-255','1','1','','','123','1');"
sample = ddl + module + field

converted = converter.convert(encrypt_old(sample), 'goods')
assert b'"source_project": "goods"' in converted
assert b'"name": "title"' in converted
assert converter.decrypt(encrypt_old(sample)) == sample
for dangerous in (sample + 'DROP TABLE `f_article`;', sample.replace("'1','1','1','123');", "'1','1','1','123');DELETE FROM `f_module`;"),
                  sample.replace("'title','标题','1'", "'title','标题','999'"),
                  sample.replace('PRIMARY KEY (`id`)', 'KEY `evil` (`title`), PRIMARY KEY (`id`)')):
    try:
        converter.convert(encrypt_old(dangerous), 'goods')
    except ValueError:
        pass
    else:
        raise AssertionError('unsafe or unsupported legacy input was accepted')
print('Legacy model converter: valid export accepted; extra SQL, unknown type and index rejected.')
