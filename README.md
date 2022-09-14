## :book: 目录

- [安装](#安装)
- [使用](#使用)

## 安装

`composer require feixun/base-search illuminate/database`

## 使用

模型

```php
//app\model\User.php
class User extends Model
{
    //在模型里面使用 HasSearch trait
    use HasSearch;

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

控制器

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
