# Granada

[![Latest Stable Version](https://img.shields.io/github/v/release/granadaorm/granada?sort=semver&style=flat-square)](https://packagist.org/packages/granadaorm/granada)
[![Total Downloads](https://img.shields.io/packagist/dt/granadaorm/granada?style=flat-square)](https://packagist.org/packages/granadaorm/granada/stats)
![Build Status](https://github.com/GranadaORM/Granada/actions/workflows/ci.yml/badge.svg)

Granada is an Active Record ORM for PHP, based on Idiorm and Paris.

Upgrading from 6.x? Read [the upgrading guide](docs/upgrading-to-7.md).
The full documentation is in [docs/index.md](docs/index.md).

## Quick example

```php
use Granada\Model;

class User extends Model
{
    public function posts()
    {
        return $this->has_many('Post');
    }
}

class Post extends Model {}

// find one user
$user = User::q()->where('name', 'John')->find_one();

// change a field
$user->first_name = 'Doe';
$user->save();

// read the user's posts
$posts = $user->posts()->find_many();
foreach ($posts as $post) {
    echo $post->content;
}
```

Granada grew out of Idiorm and Paris, and their documentation still covers most
of the query language. Granada needs PHP 8.4, so use
[docs/index.md](docs/index.md) when their documentation differs from
Granada's behavior.

## Install

Install it with composer:

```sh
composer require granadaorm/granada
```

Then point Granada at your database:

```php
require 'vendor/autoload.php';

use Granada\ORM;

ORM::configure('mysql:host=localhost;dbname=my_database');
ORM::configure('username', 'database_user');
ORM::configure('password', 'top_secret');
```

`ORM::configure()` takes the connection string, then each setting by name.
Granada works with SQLite, MySQL, PostgreSQL, SQL Server and Firebird.

## What Granada adds

### Eager loading

`with()` eager-loads the related models for the whole result in a few queries,
rather than one query per model:

```php
$results = User::q()->with('avatar', 'posts')->find_many();
```

That runs three queries:

```sql
SELECT * FROM user
SELECT * FROM avatar WHERE user_id IN (....)
SELECT * FROM posts WHERE user_id IN (....)
```

Each user then has its avatar and posts, so the loop only reads the loaded
values:

```php
foreach ($results as $result) {
    echo $result->avatar->img;
    foreach ($result->posts as $post) {
        echo $post->title;
    }
}
```

### Lazy loading

If you leave `with()` off, a relationship is loaded the first time you read it,
so this loop also works:

```php
$results = User::q()->find_many();
foreach ($results as $result) {
    echo $result->avatar->img;
}
```

If there is no avatar, the property is `null`, and reading `->img` on it raises
a warning. Check first when a relationship can be empty:

```php
if ($result->avatar) {
    echo $result->avatar->img;
}
```

### Lazy finds

`find_many_lazy()` is `find_many()` for large tables. It returns a generator
that yields one model at a time, so a report over thousands of rows does not
retain the whole result set in memory:

```php
foreach (User::q()->where('active', 1)->find_many_lazy() as $id => $user) {
    echo $user->name;
}
```

Pass a smaller chunk when you need less memory and can run more queries. Read
[the lazy finds section](docs/index.md#loading-many-records-one-at-a-time)
before you use it on a query with a join, a group, or raw SQL.

### Limit the columns an eager load fetches

`with_{relation}()` eager-loads one relationship and lets you limit or order
what it loads. Called without a callback, it does the same thing as `with()`:

```php
$car = Car::q()->with_manufactor()->find_one(1);
```

Use the closure to select columns, add conditions, or set an order:

```php
$car = Car::q()->with_manufactor(fn($q) => $q->select('id, name'))->find_one(1);
// SELECT `id`, `name` FROM `manufactor` WHERE ...

$manufactor = Manufactor::q()->with_cars(fn($q) => $q->select('id, name')->order_by_desc('name'))->find_one(1);
// SELECT `id`, `name`, `manufactor_id` FROM `car` WHERE ... ORDER BY `name` DESC
```

You can use the two forms on one query. Inside the closure, another `with_*`
call eager-loads a nested relationship:

```php
$car = Car::q()->with_manufactor(fn($q) => $q->select('name'))->with('owner')->find_one(1);

$owner = Owner::q()->with_car(fn($q) => $q->select('id, manufactor_id')
    ->with_manufactor(fn($q) => $q->select('name'))
)->find_one(1);
```

You do not have to select the key that ties the two models together. Granada
adds it for you, and selecting it yourself is also fine.

Limit a relationship query to the columns you name: `select('id, name')`. On a
`has_many_through`, qualify each column with the table name:

```php
$car = Car::q()->with_parts(fn($q) => $q->select('part.id, part.name'))->find_one(1);
```

### Nested eager loads and arguments

Nested eager loading chains relationship names with a dot: `posts.comments`
loads each user's posts, then the comments on those posts:

```php
$results = User::q()->with('posts.comments')->find_many();
// SELECT * FROM user
// SELECT * FROM post WHERE user_id IN (....)
// SELECT * FROM comments WHERE post_id IN (....)

foreach ($results as $result) {
    foreach ($result->posts as $post) {
        echo $post->title;
        foreach ($post->comments as $comment) {
            echo $comment->subject;
        }
    }
}
```

If the relationship method takes arguments, `with()` takes an array keyed by
the relationship name, with the arguments as the value. Add a `with` key to
eager-load a nested relationship:

```php
$results = User::q()->with(['posts' => ['with' => ['comments']]])->find_many();
$results = User::q()->with(['posts' => ['arg1']])->find_many(); // posts(arg1)
```

### Custom query actions

A custom query action is a real method on the model's query class. The class is
named `Query` plus the model name, extends `\Granada\Query`, and any method you
add is available through `q()`:

```php
class QueryCar extends \Granada\Query
{
    public function is_expensive(): static
    {
        return $this->where_gt('price', 1000);
    }
}

$cars = Car::q()->is_expensive()->find_many();
```

### Other query methods

Some methods take SQL that you write: `select_raw()`, `group_by_raw()`,
`order_by_raw()` and `raw_join()`. `insert()` saves a list of rows in one
transaction, `pluck()` returns one column's value, and `find_pairs()` returns a
key => value array. `delete_many()` and `update_many()` change every row that
the query matches.

### Setter methods

A `set_{column}()` setter receives each value you set for that column and
returns the value that Granada saves:

```php
// In the Model
protected function set_title($value)
{
    $this->alias = strtoupper($value);
    return $value;
}
```

`set()`, `create()` and a direct assignment all call it:

```php
// outside of the model
$content_instance->set('title', 'A title');

// several fields at once
$properties = [
    'title'   => 'A title',
    'content' => 'Some content',
];
$content_instance->set($properties);

// a direct assignment calls it too
$content_instance->title = 'A title';
```

### Mass assignment

`create()` and `set()` write every key you give them. There is no fillable or
guarded list, and Granada does not filter the keys.

If the array comes from a request, pass only the fields that the form offered
before the array reaches the model:

```php
$allowed = ['name', 'email', 'phone'];
$enquiry = Enquiry::create(array_intersect_key($data, array_flip($allowed)));
```

Granada does not remove unknown keys: the save fails with a database error if the
column does not exist. Filter the keys yourself, based on what the form offered.

### Getters and computed values

A `get_{column}()` getter transforms a column value before you read it, and a
`missing_{name}()` method computes a value that the database does not have:

```php
// In the Model

// Transform a stored column value.
protected function get_path($value)
{
    return strtolower($value);
}

// Compute a value that no column stores.
protected function missing_testing()
{
    return 'whatever';
}
...

// outside of the model
echo $content_instance->path;    // returns the lowercase path value of $content_instance
echo $content_instance->testing; // returns 'whatever', from missing_testing()
```

A method named after the property runs last, and it can return a relationship.

### Use your own result set class

Turn on `return_result_sets`, and a find returns a `Granada\ResultSet` instead
of an array. A model can name its own subclass, as long as it extends
`Granada\ResultSet` and is loaded:

```php
// In the Model
public static string $resultSetClass = 'TreeResultSet';
```

```php
// outside of the model
echo get_class(Content::q()->find_many()); // TreeResultSet
```

An eager load returns the same class: a `Content` model that names
`TreeResultSet` and has a `media` relationship returns a `TreeResultSet` of
`Content` models, with `media` as a plain `Granada\ResultSet`:

```php
Content::q()->with('media')->find_many();
```

## Granada and Paris

Granada began as a fork of Idiorm and Paris and retains their query language and
Active Record approach. It adds eager and lazy loading.

### Features

- Configuration is one connection string plus its settings.
- The fluent query API comes from [Idiorm](http://github.com/j4mie/idiorm/), and
  relationships come from Paris.
- The junction writes `attach()`, `detach()` and `sync()` manage related models
  through a link table, and `sync()` updates a `has_many`'s children.
- Eager loading and lazy finds make large results manageable.
- Granada binds values as query parameters, so the database treats them as data.
- Several connections are supported, and you can call model methods on a whole
  result set.

### History

Granada was originally developed by
[Erik Wiesenthal](https://github.com/surt), up to version 1.5. Further
development started by [Josh Marshall](https://github.com/joshbmarshall) to
release version 2.0.
