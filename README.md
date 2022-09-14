## :book: 目录

- [安装](#安装)
- [使用](#使用)

## 安装

`composer require feixun/base-search illuminate/database`

## 使用

#####模型

```php
//app\model\User.php
class User extends Model
{
    //在模型里面使用 HasSearch trait
    use HasSearch;

    //关系名称翻译
    public $relationTrans = [
        'comments' => '评论',
        'posts' => '帖子'
    ];
    //字段属性翻译 不设置的时候默认取数据表备注
    public $attributeLabels = [
        'created_at' => '创建时间',
        'updated_at' => '更新时间'
    ];

    /**
     * 模型关联phpdoc必须有@return注释  会自动根据返回类型查找符合的关联关系
     * 评论
     * @return HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /**
     * @return HasMany
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
```

#####控制器

```php
//app\controller\indexController.php
class Index
{
    public function index(Request $request)
    {
        $result = User::search([
            'page'     => 1, //页码
            'pageSize' => 10, //每页条数
            'filters'  => [
               [
                   "field_name"   => "name",
                   "field_values" => [
                       "admin"
                   ],
                   "operator"     => "LIKE",
                   "filterGroup"  => "1"
               ],
               [
                   "field_name"   => "comments",
                   "type" => "relation",
                   "filters"   => [
                        [
                            "field_name" => "content",
                            "field_values" => [
                                "这是一条评论"
                            ],
                            "operator" => "EQ"
                        ]
                   ],
                   "filterGroup"  => "1"
               ],
               [
                   "field_name"   => "gender",
                   "field_values" => [
                       "2"
                   ],
                   "operator"     => "EQ",
                   "filterGroup"  => "2"
               ],
               [
                   "field_name"   => "created_at",
                   "field_values" => [
                       "2022-09-01"
                   ],
                   "operator"     => "GTE"
               ]
            ],
            'orders' => [ //排序
                [
                    'field' => 'id',
                    'isAsc' => false
                ]
            ],
            'relations' => ['comments', 'posts']
        ]);
        return json($result);
    }
}
```

上面的查询会生成以下 SQL:

```SQL
select * from `users` where `created_at` >= "2022-09-01" and ((`name` like "admin" and exists (select * from `comments` where `users`.`id` = `comments`.`user_id` and `content` = "这是一条评论")) or (`gend
er` = 2)) order by `id` desc limit 10 offset 0
```

```SQL
select * from `comments` where `comments`.`user_id` in (2, 4)
```

```SQL
select * from `posts` where `posts`.`user_id` in (2, 4)
```

#####操作符

```php
[
    'LIKE' => '包含', //like
    'NLIKE' => '不包含', //not like
    'IS' => '为空', // is null
    'ISN' => '不为空', //is not null
    'IN' => '属于', // in
    'NIN' => '不属于', // not in
    'EQ' => '等于', // =
    'LT' => '小于', // >
    'GT' => '大于', // >
    'LTE' => '小于等于', // <=
    'GTE' => '大于等于', // >=
    'N' => '不等于',  //  <>
    'BETWEEN' => '介于', // between
];
```

#####获取字段配置信息

```php
    $fields = User::searchFields();

    // 返回结果
    // [
    //     {
    //         "type": "field",
    //         "field": "id",
    //         "label": "id",
    //         "operator": [
    //             "LIKE",
    //             "NLIKE",
    //             "IN",
    //             "NIN",
    //             "EQ",
    //             "N",
    //             "IS",
    //             "ISN"
    //         ]
    //     },
    //     {
    //         "type": "field",
    //         "field": "name",
    //         "label": "用户名",
    //         "operator": [
    //             "IN",
    //             "NIN",
    //             "EQ",
    //             "GT",
    //             "GTE",
    //             "LT",
    //             "LTE",
    //             "N",
    //             "BETWEEN"
    //         ]
    //     },
    //     {
    //         "type": "relation",
    //         "field": "comments",
    //         "label": "评论",
    //         "relation_fields": [
    //             {
    //                 "type": "field",
    //                 "field": "id",
    //                 "label": "id",
    //                 "operator": [
    //                     "LIKE",
    //                     "NLIKE",
    //                     "IN",
    //                     "NIN",
    //                     "EQ",
    //                     "N",
    //                     "IS",
    //                     "ISN"
    //                 ]
    //             },
    //             {
    //                 "type": "field",
    //                 "field": "content",
    //                 "label": "内容",
    //                 "operator": [
    //                     "IN",
    //                     "NIN",
    //                     "EQ",
    //                     "GT",
    //                     "GTE",
    //                     "LT",
    //                     "LTE",
    //                     "N",
    //                     "BETWEEN"
    //                 ]
    //             }
    //         ]
    //     },
    // ]
```
