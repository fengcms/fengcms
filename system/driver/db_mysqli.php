<?php
defined('TPL_INCLUDE') or die('Restricted access');

class db_mysqli extends database
{
    public $name = '';

    public function __construct($host, $user, $pass, $name)
    {
        $this->connect($host, $user, $pass);
        $this->select_db($name);
    }

    public function connect($host, $user, $pass)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->link_id = new mysqli($host, $user, $pass);
        $this->link_id->set_charset('utf8mb4');
    }

    public function select_db($name)
    {
        $this->name = $name;
        return $this->link_id->select_db($name);
    }

    public function query($sql, array $params = [])
    {
        if (!$params) {
            return $this->query_id = $this->link_id->query($sql);
        }
        $stmt = $this->link_id->prepare($sql);
        $types = '';
        foreach ($params as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $this->query_id = ($result ?: true);
    }

    public function list_fields($table)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table)) {
            throw new InvalidArgumentException('Invalid table name.');
        }
        return array_column($this->fetch($this->query('SHOW COLUMNS FROM `'.$table.'`')), 'Field');
    }

    public function fetch($result, $mode = 1)
    {
        if (!$result instanceof mysqli_result) { return []; }
        $rows = $result->fetch_all([MYSQLI_NUM, MYSQLI_ASSOC, MYSQLI_BOTH][$mode] ?? MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }

    public function assoc($result)
    {
        $row = $result->fetch_assoc();
        $result->free();
        return $row;
    }

    // Compatibility for trusted legacy SQL; new callers should bind parameters.
    public function escape($value)
    {
        if ($value === null) { return 'NULL'; }
        if (!is_scalar($value)) { throw new InvalidArgumentException('SQL values must be scalar.'); }
        return "'".$this->link_id->real_escape_string((string)$value)."'";
    }

    public function is_links() { return $this->link_id instanceof mysqli; }
    public function affected_rows() { return $this->link_id->affected_rows; }
    public function insert_id() { return $this->link_id->insert_id; }
    public function free_result($result = null)
    {
        if ($result instanceof mysqli_result) { $result->free(); }
    }
    public function close()
    {
        if ($this->is_links()) { $this->link_id->close(); $this->link_id = null; }
    }
    public function __destruct() { $this->close(); }
}
