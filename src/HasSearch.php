<?php

namespace Feixun\BaseSearch;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use ReflectionClass;
use support\Db;

/**
 * has search trait
 */
trait HasSearch
{
    public $_relationTrans = [];

    protected $_attributeLabels = [];

    private $_operators = [
        'LIKE' => 'like',
        'NLIKE' => 'not like',
        'IS' => 'is',
        'ISN' => 'is not',
        'IN' => 'in',
        'NIN' => 'not in',
        'EQ' => '=',
        'LT' => '<',
        'GT' => '>',
        'LTE' => '<=',
        'GTE' => '>=',
        'N' => '<>',
        'BETWEEN' => 'between',
    ];

    protected $_operatorLabels = [
        'LIKE' => '包含',
        'NLIKE' => '不包含',
        'IS' => '为空',
        'ISN' => '不为空',
        'IN' => '属于',
        'NIN' => '不属于',
        'EQ' => '等于',
        'LT' => '小于',
        'GT' => '大于',
        'LTE' => '小于等于',
        'GTE' => '大于等于',
        'N' => '不等于',
        'BETWEEN' => '介于',
    ];

    public $operatorGroup = [
        'string' => ['IN', 'NIN', 'EQ', 'GT', 'GTE', 'LT', 'LTE', 'N', 'BETWEEN'],
        'integer' => ['LIKE', 'NLIKE', 'IN', 'NIN', 'EQ', 'N', 'IS', 'ISN'],
        'date' => ['EQ', 'GT', 'GTE', 'LT', 'LTE', 'N', 'BETWEEN', 'IS', 'ISN']
    ];

    /**
     * 获取操作标签
     * @return mixed|string[]
     */
    public function getOperatorLabels()
    {
        return $this->_operatorLabels;
    }

    /**
     * @return string
     */
    public function getFullTable(): string
    {
        return  Db::connection()->getTablePrefix().$this->getTable();
    }

    /**
     * 根据类型获取操作符
     * @param string $type
     * @return string[]
     */
    protected function getOperator(string $type = 'string'): array
    {
        if (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'])) {
            return $this->operatorGroup['integer'];
        } elseif (in_array($type, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'])) {
            return $this->operatorGroup['string'];
        } elseif (in_array($type, ['date', 'timestamp', 'datetime', 'time'])) {
            return $this->operatorGroup['date'];
        }
        return ['EQ', 'N'];
    }

    /**
     * get model relations
     * @return array
     */
    public function getRelationFields(): array
    {
        $maps = [];
        $tablePrefix = Db::connection()->getTablePrefix();
        $class = new ReflectionClass(self::class);
        foreach ($class->getMethods() as $method) {
            $returnType = $method->getReturnType();
            if (!is_null($returnType) && strpos($returnType->getName(), 'Illuminate\Database\Eloquent\Relations') !== false) {
                $relationTable = call_user_func([$this, $method->getName()])->getRelated()->getTable();
                $maps[] = [
                    'type' => 'relation',
                    'field' => $method->getName(),
                    'label' => $this->_relationTrans[$method->getName()] ?? $method->getName(),
                    'relation_fields' => $this->getFullColumns($tablePrefix.$relationTable)
                ];
            }
        }
        return $maps;
    }

    /**
     * @param array $params
     * @return array
     */
    public static function search(array $params = []): array
    {
        return (new static())->searchByParams($params);
    }

    /**
     * 获取可查询字段
     * @return array
     */
    public static function searchFields(): array
    {
        return (new static())->getSearchFields();
    }

    /**
     * 查询搜索字段
     * @return array
     */
    public function getSearchFields(): array
    {
        $normalFields = $this->getFullColumns();
        $relationFields = $this->getRelationFields();
        return array_merge($normalFields, $relationFields);
    }

    /**
     * 获取全部字段信息
     * @param string $table
     * @return array
     */
    public function getFullColumns(string $table = ''): array
    {
        $table = $table ?: $this->getFullTable();
        $columns = Db::connection()->select('show FULL COLUMNS FROM ' . $table);
        return $this->parseTableFields($this->objectToArray($columns));
    }

    /**
     * 解析表单字段
     * @param array $columns
     * @return array
     */
    private function parseTableFields(array $columns = []): array
    {
        $tableFields = [];
        foreach ($columns as $value) {
            $fieldType = preg_replace('/(\(.*\))?/', '', $value['Type']);
            $fieldOperator = $this->getOperator($fieldType);
            $tableFields[] = [
                'type' => 'field',
                'field' => $value['Field'],
//                'field_type' => $fieldType,
                'label' => $this->_attributeLabels[$value['Field']] ?? ($value['Comment'] ?: $value['Field']),
                'operator' => $fieldOperator
            ];
        }
        return $tableFields;
    }



    /**
     * 根据条件查询
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     */
    public function searchByParams(array $params = []): array
    {
        $tableColumn = $this->getFullColumns();
        $effectFields = array_column($tableColumn, 'field');

        /** @var Builder $query */
        $query = static::query();
        $filters = $params['filters'] ?? [];
        $orders = $params['orders'] ?? [
            ['field' => $this->primaryKey, 'isAsc' => true]
        ];
        $relations = $params['relations'] ?? [];

        foreach ($filters as $filter) {
            if (!$this->checkFilter($filter)) {
                throw new InvalidArgumentException("筛选格式无效");
            }
        }

        [$filterBase, $filterGroup] = $this->filterGroup($filters);
        foreach ($filterBase as $item) {
            $operator = $this->_operators[$item['operator'] ?? 'EQ'];
            $query = $this->handleWhere($query, $item['field_name'], $operator, $item['field_values']);
        }

        //TODO 待优化
        if (!empty($filterGroup)) {
            $query->where(function (Builder $query) use ($filterGroup) {
                foreach ($filterGroup as $group) {
                    $query->orWhere(function (Builder $query) use ($group) {
                        foreach ($group as $item) {
                            if (isset($item['type']) && $item['type'] === 'relation') {
                                $query->whereHas($item['field_name'], function (Builder $query) use ($item) {
                                    if (isset($item['filters']) && is_array($item['filters']) && !empty($item['filters'])) {
                                        foreach ($item['filters'] as $filter) {
                                            $operator = $this->_operators[$filter['operator'] ?? 'EQ'];
                                            $query = $this->handleWhere($query, $filter['field_name'], $operator, $filter['field_values']);
                                        }
                                    }
                                });
                            } else {
                                $operator = $this->_operators[$item['operator'] ?? 'EQ'];
                                $query = $this->handleWhere($query, $item['field_name'], $operator, $item['field_values']);
                            }
                        }
                    });
                }
            });
        }

        $total = (clone $query)->count();

        //查询关联信息
        if(!empty($relations)) {
            $allRelations = array_column($this->getRelationFields(), 'field');
            $_relations = [];
            foreach($relations as $relation) {
                if(in_array($relation, $allRelations)) {
                    $_relations[] = $relation;
                }
            }
            if(!empty($_relations)) {
                $query->with($_relations);
            }
        }

        //分页
        $query->when((isset($params['page'])), function (Builder $query) use ($params) {
            $page = max(intval($params['page']), 1);
            $pageSize = $params['pageSize'] ?? 10;
            $pageSize = intval($pageSize);
            $query->offset(($page - 1) * $pageSize)->limit($pageSize);
        });

        //排序
        foreach ($orders as $order) {
            if (isset($order['field']) && in_array($order['field'], $effectFields)) {
                $query->orderBy($order['field'], ($order['isAsc'] ?? true) ? 'asc' : 'desc');
            }
        }
        return ['total' => $total, 'list' => $query->get()];
    }

    /**
     * 处理where语句
     * @param Builder $query
     * @param string $field
     * @param string $operator
     * @param $value
     * @return Builder
     */
    protected function handleWhere(Builder $query, string $field, string $operator, $value): Builder
    {
        switch ($operator) {
            case 'between':
                $query->whereBetween($field, $value);
                break;
            case 'in':
                $query->whereIn($field, $value);
                break;
            case 'not in':
                $query->whereNotIn($field, $value);
                break;
            case 'is':
                $query->whereNull($field);
                break;
            case 'is not':
                $query->whereNotNull($field);
                break;
            default:
                if (in_array($operator, ['=', 'like', 'not like', '>', '<', '>=', '<=', '<>'])) {
                    $query->where($field, $operator, is_array($value) ? ($value[0] ?? '') : $value);
                }
        }
        return $query;
    }

    /**
     * 检查是否有效filter
     * @param array $filter
     * @return bool
     * @throws InvalidArgumentException
     */
    public function checkFilter(array $filter = []): bool
    {
        if (empty($filter)) return false;

        $type = $filter['type'] ?? 'field';
        if (!in_array($type, ['field', 'relation'])) {
            throw new InvalidArgumentException("筛选类型错误");
        }

        if ($type === 'field') {
            return isset($filter['field_name'])
                && isset($filter['field_values'])
                && is_array($filter['field_values'])
                && isset($filter['operator'])
                && in_array($filter['operator'], array_keys($this->_operators));
        }

        if ($type === 'relation') {
            return isset($filter['field_name']) && isset($filter['filters']) && is_array($filter['filters']);
        }

        return false;

    }

    /**
     * 筛选分组
     * @param array $data
     * @return array[]
     */
    protected function filterGroup(array $data = []): array
    {
        $base = $group = [];
        foreach ($data as $item) {
            if (isset($item['filterGroup'])) {
                $group[] = $item;
            } else {
                $base[] = $item;
            }
        }

        $newGroup = [];
        foreach ($group as $item) {
            $newGroup[$item['filterGroup']] = array_merge(($newGroup[$item['filterGroup']] ?? []), [$item]);
        }

        return [$base, $newGroup];
    }

    /**
     * Initialize the trait
     *
     * @return void
     */
    protected function initializeHasSearch()
    {
        // Automatically create a random token
        $this->_relationTrans = $this->relationTrans ?? [];
        $this->_attributeLabels = $this->attributeLabels ?? [];
    }

    /**
     * Convert the model instance to an array.
     *
     * @param $data
     * @return array
     */
    private function objectToArray($data): array
    {
        return json_decode(json_encode($data), true);
    }
}
