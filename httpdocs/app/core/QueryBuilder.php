<?php
namespace App\Core;

class QueryBuilder {
    private $allowedColumns = null;
    private $allowedTables = null;
    
    /**
     * Set allowed columns whitelist
     * @param array $columns Allowed column names
     * @return self
     */
    public function setAllowedColumns(array $columns): self {
        $this->allowedColumns = $columns;
        return $this;
    }
    
    /**
     * Set allowed tables whitelist
     * @param array $tables Allowed table names
     * @return self
     */
    public function setAllowedTables(array $tables): self {
        $this->allowedTables = $tables;
        return $this;
    }
    
    /**
     * Validate column name
     * @param string $columnName Column name
     * @return bool True if valid
     */
    private function validateColumn(string $columnName): bool {
        require_once __DIR__ . '/Security/SQLInjectionProtection.php';
        
        if ($this->allowedColumns !== null) {
            return \App\Core\Security\SQLInjectionProtection::validateColumnName($columnName, $this->allowedColumns);
        }
        
        // Basic validation - no dangerous characters
        $sanitized = \App\Core\Security\SQLInjectionProtection::sanitizeColumnName($columnName);
        return $sanitized === $columnName;
    }
    
    /**
     * Validate table name
     * @param string $tableName Table name
     * @return bool True if valid
     */
    private function validateTable(string $tableName): bool {
        require_once __DIR__ . '/Security/SQLInjectionProtection.php';
        
        if ($this->allowedTables !== null) {
            return \App\Core\Security\SQLInjectionProtection::validateTableName($tableName, $this->allowedTables);
        }
        
        // Basic validation - no dangerous characters
        $sanitized = \App\Core\Security\SQLInjectionProtection::sanitizeTableName($tableName);
        return $sanitized === $tableName;
    }
    protected $db;
    protected $table;
    protected $select = ['*'];
    protected $from = [];
    protected $joins = [];
    protected $wheres = [];
    protected $groups = [];
    protected $havings = [];
    protected $orders = [];
    protected $limit = null;
    protected $offset = null;
    protected $bindings = [];
    protected $distinct = false;
    protected $aggregate = null;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function table(string $table): self {
        if (!$this->validateTable($table)) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }
        $this->from = [$table];
        return $this;
    }
    
    public function from(string $table, ?string $alias = null): self {
        // Extract table name if alias is provided in table string (e.g., "table AS alias")
        $tableParts = explode(' ', trim($table));
        $tableName = $tableParts[0];
        
        // Validate table name
        if (!$this->validateTable($tableName)) {
            throw new \InvalidArgumentException("Invalid table name: {$tableName}");
        }
        
        if ($alias) {
            // Validate alias name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
                throw new \InvalidArgumentException("Invalid alias name: {$alias}");
            }
            $this->from[] = "{$tableName} AS {$alias}";
        } elseif (strpos($table, ' ') !== false) {
            // Table string contains space, might be "table AS alias" format
            // Validate all parts
            foreach ($tableParts as $part) {
                if (strtoupper($part) !== 'AS' && !$this->validateTable($part)) {
                    throw new \InvalidArgumentException("Invalid table name or alias: {$part}");
                }
            }
            $this->from[] = $table;
        } else {
            $this->from[] = $table;
        }
        return $this;
    }
    
    public function select($columns = ['*']): self {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $this->select = $columns;
        return $this;
    }
    
    public function addSelect($columns): self {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $this->select = array_merge($this->select, $columns);
        return $this;
    }
    
    public function selectRaw(string $expression): self {
        $this->select[] = $expression;
        return $this;
    }
    
    public function distinct(): self {
        $this->distinct = true;
        return $this;
    }
    
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'INNER'): self {
        if (func_num_args() === 3) {
            $second = $operator;
            $operator = '=';
        }
        
        $tableParts = explode(' ', $table);
        $tableName = $tableParts[0];
        $tableAlias = $tableParts[1] ?? $tableName;
        
        // Validate table name
        if (!$this->validateTable($tableName)) {
            throw new \InvalidArgumentException("Invalid table name in join: {$tableName}");
        }
        
        // Validate join columns (allow dot notation for table.column)
        $firstColumn = strpos($first, '.') !== false ? explode('.', $first)[1] : $first;
        $secondColumn = strpos($second, '.') !== false ? explode('.', $second)[1] : $second;
        
        if (!$this->validateColumn($firstColumn) || !$this->validateColumn($secondColumn)) {
            throw new \InvalidArgumentException("Invalid column name in join condition: {$first} or {$second}");
        }
        
        $this->joins[] = [
            'type' => $type,
            'table' => $tableName,
            'alias' => $tableAlias,
            'first' => $first,
            'operator' => $operator ?? '=',
            'second' => $second
        ];
        return $this;
    }
    
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
    
    public function innerJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self {
        return $this->join($table, $first, $operator, $second, 'INNER');
    }
    
    public function where(string $column, $operator = null, $value = null, string $boolean = 'AND'): self {
        // Sanitize column name to prevent SQL injection (allow dot notation for table.column)
        $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
        if (!$this->validateColumn($columnName)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        // Validate operator
        $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        if (!in_array(strtoupper($operator), $validOperators) && !is_null($value)) {
            $operator = '=';
        }
        
        if (is_null($value)) {
            $this->wheres[] = [
                'type' => 'Null',
                'column' => $column,
                'boolean' => $boolean
            ];
        } else {
            $this->wheres[] = [
                'type' => 'Basic',
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
                'boolean' => $boolean
            ];
            $this->addBinding($value, 'where');
        }
        
        return $this;
    }
    
    public function orWhere(string $column, $operator = null, $value = null): self {
        return $this->where($column, $operator, $value, 'OR');
    }
    
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self {
        // Sanitize column name to prevent SQL injection (allow dot notation for table.column)
        $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
        if (!$this->validateColumn($columnName)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        
        $this->wheres[] = [
            'type' => $not ? 'NotIn' : 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];
        $this->addBinding($values, 'where');
        return $this;
    }
    
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self {
        return $this->whereIn($column, $values, $boolean, true);
    }
    
    public function whereBetween(string $column, array $values, string $boolean = 'AND', bool $not = false): self {
        // Sanitize column name to prevent SQL injection (allow dot notation for table.column)
        $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
        if (!$this->validateColumn($columnName)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        
        $this->wheres[] = [
            'type' => $not ? 'NotBetween' : 'Between',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean
        ];
        $this->addBinding($values, 'where');
        return $this;
    }
    
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self {
        // Sanitize column name to prevent SQL injection (allow dot notation for table.column)
        $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
        if (!$this->validateColumn($columnName)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        
        $this->wheres[] = [
            'type' => $not ? 'NotNull' : 'Null',
            'column' => $column,
            'boolean' => $boolean
        ];
        return $this;
    }
    
    public function whereNotNull(string $column, string $boolean = 'AND'): self {
        return $this->whereNull($column, $boolean, true);
    }
    
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => $boolean
        ];
        $this->addBinding($bindings, 'where');
        return $this;
    }
    
    public function groupBy(...$columns): self {
        // Validate each column name
        foreach ($columns as $column) {
            $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
            if (!$this->validateColumn($columnName)) {
                throw new \InvalidArgumentException("Invalid column name in groupBy: {$column}");
            }
        }
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }
    
    public function having(string $column, $operator = null, $value = null, string $boolean = 'AND'): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        // Sanitize column name to prevent SQL injection (allow dot notation for table.column)
        $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
        if (!$this->validateColumn($columnName)) {
            throw new \InvalidArgumentException("Invalid column name in having: {$column}");
        }
        
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];
        $this->addBinding($value, 'having');
        return $this;
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): self {
        // Sanitize column name to prevent SQL injection (allow dot notation for table.column)
        $columnName = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
        if (!$this->validateColumn($columnName)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        
        // Validate direction
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction
        ];
        return $this;
    }
    
    public function orderByDesc(string $column): self {
        return $this->orderBy($column, 'DESC');
    }
    
    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }
    
    public function paginate(int $perPage, int $page = 1): self {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }
    
    public function count(string $column = '*'): int {
        $this->aggregate = ['function' => 'count', 'column' => $column];
        $result = $this->get();
        return $result ? (int)$result[0]['aggregate'] : 0;
    }
    
    public function sum(string $column): float {
        $this->aggregate = ['function' => 'sum', 'column' => $column];
        $result = $this->get();
        return $result ? (float)$result[0]['aggregate'] : 0;
    }
    
    public function avg(string $column): float {
        $this->aggregate = ['function' => 'avg', 'column' => $column];
        $result = $this->get();
        return $result ? (float)$result[0]['aggregate'] : 0;
    }
    
    public function max(string $column) {
        $this->aggregate = ['function' => 'max', 'column' => $column];
        $result = $this->get();
        return $result ? $result[0]['aggregate'] : null;
    }
    
    public function min(string $column) {
        $this->aggregate = ['function' => 'min', 'column' => $column];
        $result = $this->get();
        return $result ? $result[0]['aggregate'] : null;
    }
    
    public function raw(string $sql, array $bindings = []): self {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'boolean' => 'AND'
        ];
        $this->addBinding($bindings, 'where');
        return $this;
    }
    
    public function get(): array {
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->getBindings());
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    
    public function first(): ?array {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }
    
    public function toSql(): string {
        if ($this->aggregate) {
            return $this->compileAggregate();
        }
        
        $sql = 'SELECT ';
        
        if ($this->distinct) {
            $sql .= 'DISTINCT ';
        }
        
        $sql .= implode(', ', $this->select);
        $sql .= ' FROM ' . implode(', ', $this->from);
        
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $tableClause = $join['table'];
                if (isset($join['alias']) && $join['alias'] !== $join['table']) {
                    $tableClause .= ' AS ' . $join['alias'];
                }
                $sql .= " {$join['type']} JOIN {$tableClause} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        
        if (!empty($this->havings)) {
            $sql .= ' HAVING ' . $this->compileHavings();
        }
        
        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }
        
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }
    
    protected function compileAggregate(): string {
        $function = strtoupper($this->aggregate['function']);
        $column = $this->aggregate['column'];
        
        $fromTables = [];
        foreach ($this->from as $from) {
            $fromTables[] = $from;
        }
        $sql = "SELECT {$function}({$column}) AS aggregate FROM " . implode(', ', $fromTables);
        
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $tableClause = $join['table'];
                if (isset($join['alias']) && $join['alias'] !== $join['table']) {
                    $tableClause .= ' AS ' . $join['alias'];
                }
                $sql .= " {$join['type']} JOIN {$tableClause} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        
        return $sql;
    }
    
    protected function compileWheres(): string {
        $whereClauses = [];
        
        foreach ($this->wheres as $index => $where) {
            $boolean = $index > 0 ? $where['boolean'] . ' ' : '';
            
            switch ($where['type']) {
                case 'Basic':
                    $whereClauses[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    break;
                case 'Null':
                    $whereClauses[] = $boolean . "{$where['column']} IS NULL";
                    break;
                case 'NotNull':
                    $whereClauses[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
                case 'In':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $whereClauses[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    break;
                case 'NotIn':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $whereClauses[] = $boolean . "{$where['column']} NOT IN ({$placeholders})";
                    break;
                case 'Between':
                    $whereClauses[] = $boolean . "{$where['column']} BETWEEN ? AND ?";
                    break;
                case 'NotBetween':
                    $whereClauses[] = $boolean . "{$where['column']} NOT BETWEEN ? AND ?";
                    break;
                case 'Raw':
                    $whereClauses[] = $boolean . $where['sql'];
                    if (isset($where['bindings']) && is_array($where['bindings'])) {
                        foreach ($where['bindings'] as $binding) {
                            $this->bindings[] = $binding;
                        }
                    }
                    break;
            }
        }
        
        return implode(' ', $whereClauses);
    }
    
    protected function compileHavings(): string {
        $havingClauses = [];
        
        foreach ($this->havings as $index => $having) {
            $boolean = $index > 0 ? $having['boolean'] . ' ' : '';
            $havingClauses[] = $boolean . "{$having['column']} {$having['operator']} ?";
        }
        
        return implode(' ', $havingClauses);
    }
    
    protected function addBinding($value, string $type = 'where'): void {
        if (is_array($value)) {
            $this->bindings = array_merge($this->bindings, $value);
        } else {
            $this->bindings[] = $value;
        }
    }
    
    public function getBindings(): array {
        return $this->bindings;
    }
    
    public function insert(array $data) {
        if (empty($this->from)) {
            throw new \Exception('Table not specified. Use from() or table() method.');
        }
        
        $table = $this->from[0];
        $tableName = $this->extractTableName($table);
        
        // For payment_transactions table, use centralized mapper
        if ($tableName === 'payment_transactions') {
            require_once __DIR__ . '/DataMapper/PaymentTransactionMapper.php';
            $data = \App\Core\DataMapper\PaymentTransactionMapper::filterAndMap($data);
        }
        
        $keys = array_keys($data);
        if (empty($keys)) {
            throw new \Exception('No data provided for insert.');
        }
        
        $fields = implode(',', $keys);
        $placeholders = ':' . implode(', :', $keys);
        
        $sql = "INSERT INTO {$tableName} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    public function update(array $data) {
        if (empty($this->from)) {
            throw new \Exception('Table not specified. Use from() or table() method.');
        }
        
        $table = $this->from[0];
        $tableName = $this->extractTableName($table);
        
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$tableName} SET {$setClause}";
        
        if (!empty($this->wheres)) {
            // Compile WHERE clause with named parameters to match SET clause
            $whereClause = $this->compileWheresForUpdate();
            $sql .= ' WHERE ' . $whereClause;
            
            // Merge WHERE bindings with data, using named parameters
            $whereBindings = $this->getBindingsForUpdate();
            $data = array_merge($data, $whereBindings);
        }
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($data)) {
            return $stmt->rowCount();
        }
        return 0;
    }
    
    /**
     * Compile WHERE clause for UPDATE with named parameters
     * @return string
     */
    protected function compileWheresForUpdate(): string {
        $whereClauses = [];
        $paramIndex = 0;
        
        foreach ($this->wheres as $index => $where) {
            $boolean = $index > 0 ? $where['boolean'] . ' ' : '';
            
            switch ($where['type']) {
                case 'Basic':
                    $paramName = 'where_' . $paramIndex++;
                    $whereClauses[] = $boolean . "{$where['column']} {$where['operator']} :{$paramName}";
                    break;
                case 'Null':
                    $whereClauses[] = $boolean . "{$where['column']} IS NULL";
                    break;
                case 'NotNull':
                    $whereClauses[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
                case 'In':
                    $placeholders = [];
                    foreach ($where['values'] as $val) {
                        $paramName = 'where_' . $paramIndex++;
                        $placeholders[] = ":{$paramName}";
                    }
                    $whereClauses[] = $boolean . "{$where['column']} IN (" . implode(', ', $placeholders) . ")";
                    break;
                case 'NotIn':
                    $placeholders = [];
                    foreach ($where['values'] as $val) {
                        $paramName = 'where_' . $paramIndex++;
                        $placeholders[] = ":{$paramName}";
                    }
                    $whereClauses[] = $boolean . "{$where['column']} NOT IN (" . implode(', ', $placeholders) . ")";
                    break;
                case 'Between':
                    $paramName1 = 'where_' . $paramIndex++;
                    $paramName2 = 'where_' . $paramIndex++;
                    $whereClauses[] = $boolean . "{$where['column']} BETWEEN :{$paramName1} AND :{$paramName2}";
                    break;
                case 'NotBetween':
                    $paramName1 = 'where_' . $paramIndex++;
                    $paramName2 = 'where_' . $paramIndex++;
                    $whereClauses[] = $boolean . "{$where['column']} NOT BETWEEN :{$paramName1} AND :{$paramName2}";
                    break;
                case 'Raw':
                    $whereClauses[] = $boolean . $where['sql'];
                    break;
            }
        }
        
        return implode(' ', $whereClauses);
    }
    
    /**
     * Get bindings for UPDATE with named parameters
     * @return array
     */
    protected function getBindingsForUpdate(): array {
        $bindings = [];
        $paramIndex = 0;
        
        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'Basic':
                    $paramName = 'where_' . $paramIndex++;
                    $bindings[$paramName] = $where['value'];
                    break;
                case 'In':
                case 'NotIn':
                    foreach ($where['values'] as $val) {
                        $paramName = 'where_' . $paramIndex++;
                        $bindings[$paramName] = $val;
                    }
                    break;
                case 'Between':
                case 'NotBetween':
                    $paramName1 = 'where_' . $paramIndex++;
                    $paramName2 = 'where_' . $paramIndex++;
                    $bindings[$paramName1] = $where['values'][0];
                    $bindings[$paramName2] = $where['values'][1];
                    break;
                case 'Raw':
                    if (isset($where['bindings']) && is_array($where['bindings'])) {
                        foreach ($where['bindings'] as $binding) {
                            $paramName = 'where_' . $paramIndex++;
                            $bindings[$paramName] = $binding;
                        }
                    }
                    break;
            }
        }
        
        return $bindings;
    }
    
    public function delete() {
        if (empty($this->from)) {
            throw new \Exception('Table not specified. Use from() or table() method.');
        }
        
        $table = $this->from[0];
        $tableName = $this->extractTableName($table);
        
        $sql = "DELETE FROM {$tableName}";
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($this->getBindings())) {
            return $stmt->rowCount();
        }
        return 0;
    }
    
    protected function extractTableName(string $table): string {
        $parts = explode(' ', trim($table));
        return $parts[0];
    }
    
    public function reset(): self {
        $this->select = ['*'];
        $this->from = [];
        $this->joins = [];
        $this->wheres = [];
        $this->groups = [];
        $this->havings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->bindings = [];
        $this->distinct = false;
        $this->aggregate = null;
        return $this;
    }
}

