<?php
require_once dirname(__FILE__) . '/../helpers/Database.php';

class Model {
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;

    public function find($id) {
        return db()->query("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1", [$id])->fetch();
    }

    public function findOrFail($id) {
        $result = $this->find($id);
        if (!$result) {
            http_response_code(404);
            throw new Exception('Record not found');
        }
        return $result;
    }

    public function all($orderBy = null, $direction = 'DESC') {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy} {$direction}";
        return db()->query($sql)->fetchAll();
    }

    public function create($data) {
        $data = $this->filterFillable($data);
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        db()->query($sql, array_values($data));
        return db()->lastInsertId();
    }

    public function update($id, $data) {
        $data = $this->filterFillable($data);
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $set = [];
        $values = [];
        foreach ($data as $col => $val) {
            $set[] = "{$col} = ?";
            $values[] = $val;
        }
        $values[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = ?";
        db()->query($sql, $values);
        return $this->rowCount() > 0;
    }

    public function delete($id) {
        db()->query("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?", [$id]);
        return db()->rowCount() > 0;
    }

    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        return db()->query("SELECT * FROM {$this->table} WHERE {$column} {$operator} ?", [$value])->fetchAll();
    }

    public function whereFirst($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        return db()->query("SELECT * FROM {$this->table} WHERE {$column} {$operator} ? LIMIT 1", [$value])->fetch();
    }

    public function count() {
        return db()->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }

    public function rowCount() {
        return db()->rowCount();
    }

    public function paginate($page, $perPage = 25, $orderBy = 'created_at', $direction = 'DESC') {
        $total = db()->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        $pagination = paginate($page, $perPage, $total, $_SERVER['REQUEST_URI']);
        $results = db()->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction} LIMIT ? OFFSET ?", [$perPage, $pagination['offset']])->fetchAll();
        return [
            'data' => $results,
            'pagination' => $pagination
        ];
    }

    protected function filterFillable($data) {
        if (empty($this->fillable)) return $data;
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
