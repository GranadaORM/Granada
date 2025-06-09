# GranadaORM

## Documentation

Please note this documentation is not yet complete.
We're working on it!
Most of the functionality is based on Idiorm/Paris, so their documentation can be used as a starting basis.
For starters we'll have some more complex examples here, and as features are added.

If you want to contribute to the documentation, please feel free to submit a pull request!

## Querying

Note: in the examples below, it shows the query as executed on the SQL database.
Internally it does use placeholders so you only need to escape data if you are sending your data inline in a raw query.

## By primary key

To load a model by its primary key, use the `find_one()` function, specifying the primary key:

```php
$item = User::find_one($id);
// SELECT * FROM user WHERE id=$id;
echo $item->name;
```

## Loading a single record

To load a single record of a model, set the query parameters then use `find_one()` function to limit to one record:

```php
$item = User::find_one();
// SELECT * FROM user LIMIT 1;
echo $item->name;
```

## Loading multiple records

Get a number of records from the database, in an iterable list, by using the `find_many()` function.

```php
$items = User::find_many();
// SELECT * FROM user;
foreach ($items as $item) {
   echo $item->name;
}
```

## Loading multiple records, mapped to a structure

Get a number of records from the database, in a custom mapped structure, by using the `find_map()` function.

```php
$item = User::find_map(fn ($e) => (object)[
    'id'      => $e->id,
    'name'    => $e->first_name . ' ' . $e->last_name,
    'isChild' => $e->age < 18,
]);
foreach ($items as $item) {
    echo $item->isChild;
}
```

## Limiting results

Specify the number of results you want to load using the `limit()` and `offset()` functions:

```php
$items = User::offset(15)
    ->limit(5)
    ->find_many();
// SELECT * FROM user LIMIT 5 OFFSET 15;
```

### Filtering results

Add comparative filters to the query by using various functions.
As you can see they are by default all reducing (uses AND).

```php
$items = User::where('class', 'Test')
    ->where_gt('age', 5)
    ->where_lt('age', 10)
    ->where_gte('friends', 2)
    ->where_lte('friends', 4)
    ->where_not_equal('enabled', 1)
    ->where_like('first_name', '%red%')
    ->where_not_like('first_name', '%blue%')
    ->where_null('date_completed')
    ->where_not_null('date_commenced')
    ->where_raw('id IN (SELECT user_id FROM class_enrolment WHERE class_id=?)', $class_id)
    ->find_many();
// SELECT * FROM `user` WHERE
// `class` = 'Test'
// AND `age` > 5
// AND `age` < 10
// AND `friends` >= 2
// AND `friends` <= 4
// AND `enabled` != 1
// AND `first_name` LIKE "%red%"
// AND `first_name` NOT LIKE "%blue%"
// AND `date_completed` IS NULL
// AND `date_commenced` IS NOT NULL
// AND `id` IN (SELECT user_id FROM class_enrolment WHERE class_id=$class_id);
```

Also available is the option to put the variable name in the called function. For example:

```php
$items = User::where_class('Test')
    ->where_age_gt(5)
    ->where_age_lt(10)
    ->where_friends_gte(2)
    ->where_friends_lte(4)
    ->where_enabled_not_equal(1)
    ->where_first_name_like('%red%')
    ->where_first_name_not_like('%blue%')
    ->where_date_completed_null()
    ->where_date_commenced_not_null()
    ->find_many();
```

This is useful when using IDE and you put in docblocks to inform the IDE of the functions existing.
For example:

```php
/**
 * @method static where_enabled($value) Add WHERE enabled = "$value"
 * @method static where_enabled_not_equal($value) Add WHERE enabled != "$value"
 * @method static where_enabled_like($value) Add WHERE enabled LIKE "$value"
 * @method static where_enabled_not_like($value) Add WHERE enabled NOT LIKE "$value"
 * @method static where_enabled_gt($value) Add WHERE enabled > "$value"
 * @method static where_enabled_lt($value) Add WHERE enabled < "$value"
 * @method static where_enabled_gte($value) Add WHERE enabled >= "$value"
 * @method static where_enabled_lte($value) Add WHERE enabled <= "$value"
 */
```

### Subselects for in and not in

Instead of doing multiple queries or raw queries to perform a subselect, you can send a filter to the list instead of an array and it will use a subselect at the database.

For example, instead of:

```php
$items = User::where_id_in(
    Invoice::where_is_paid(false)->find_pairs('user_id', 'user_id')
)->find_many();
// SELECT user_id FROM invoice WHERE is_paid = 0
// SELECT * FROM user WHERE id IN (1,2,3,4,5,6,7,8,9,10)

// Do instead
$items = User::where_id_in(
    Invoice::where_is_paid(false)->select('user_id')
)->find_many();

// SELECT * FROM user WHERE id IN (SELECT user_id FROM invoice WHERE is_paid = 0)
```

### Some built-in OR filters

To reduce complexity of the OR filtering (below) a few shortened filters are available to check whether a field is NULL as well.

For example:

```php
$items = User::where_lt_or_null('age', 5)
    ->where_gt_or_null('age', 10)
    ->where_gte_or_null('friends', 2)
    ->where_lte_or_null('friends', 4)
    ->where_not_in_or_null('age', [3, 4, 5])
    ->find_many();
```

### Using OR in filters

Since the default is to reduce results by using AND's, we use the `where_any_is()` function to add a group of filters that are OR'd together.

A simple OR, shown mixed with an AND filter:

```php
$items = User::where_any_is(
    [
        ['name' => 'Joe'],
        ['name' => 'Fred'],
    ])
    ->where('enabled', 1)
    ->find_many();
    // SELECT * FROM `user` WHERE (`name` = 'Joe' OR `name` = 'Fred' ) AND `enabled` = 1
```

An OR, with a non-default operator

```php
$items = User::where_any_is(
    [
        ['name' => 'Joe'],
        ['name' => 'Fred'],
    ], '!=')
    ->where('enabled', 1)
    ->find_many();
    // SELECT * FROM `user` WHERE (`name` != 'Joe' OR `name` != 'Fred' ) AND `enabled` = 1
```

Adding some AND comparisons inside the OR

```php
$items = User::where_any_is(
    [
        ['name' => 'Joe'],
        ['name' => 'Fred', 'age' => 20],
    ])->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))
```

Overriding the comparison for one data type:

```php
$items = User::where_any_is(
    [
        ['name' => 'Joe', 'age' => 10],
        ['name' => 'Fred', 'age' => 20],
    ], array('age' => '>')
    )->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))
```

Overriding the comparison for all data types:

```php
$items = User::where_any_is(
    [
        ['score' => '5', 'age' => 10],
        ['score' => '15', 'age' => 20],
    ], '>')->find_many();
    // SELECT * FROM `user` WHERE (( `score` > '5' AND `age` > '10' ) OR ( `score` > '15' AND `age` > '20' ))
```

You can use NULL values in comparisons:

```php
$items = User::where_any_is(
    [
        ['name' => 'Joe', 'age' => NULL],
        ['name' => NULL, 'age' => 20],
    ])->find_many();
// SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` IS NULL ) OR ( `name` IS NULL AND `age` = '20' ))

They also work with the `!=` operator:

```php
$items = User::where_any_is(
    [
        ['name' => 'Joe', 'age' => NULL],
        ['name' => NULL, 'age' => 20],
    ], '!=')
    ->find_many();
    // SELECT * FROM `user` WHERE (( `name` != 'Joe' AND `age` IS NOT NULL ) OR ( `name` IS NOT NULL AND `age` != '20' ))
```

Pass an array to convert it into an IN or NOT IN (depending on the operator):

```php
$items = User::where_any_is(
    [
        [
            'name' => 'Joe',
            'age' => [18, 19],
        ],
        [
            'name' => ['Bob', 'Jack'],
            'age' => 20,
        ],
    ], array( 'age' => '!=')
    )->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` NOT IN ('18', '19') ) OR ( `name` IN ('Bob', 'Jack') AND `age` != '20' ))
```

Optionally apply a where, use this to avoid breaking long chains. Can also be used for order:

```php
$min_age = 5;
$order = true;
$items = User::where('class', 'Test')
        ->onlyif(false, function($q) { // Will skip this filter
            return $q->where_lt('age', 10);
        })
        ->onlyif($min_age > 0, function($q) use ($min_age) { // Will apply this filter only when min_age is greater than 0
            return $q->where_gt('age', $min_age);
        })
        ->onlyif($order, function($q) {
            return $q->order_by_asc('age);
        })
        ->find_many();
// SELECT * FROM `user` WHERE `class` = 'Test' AND `age` > 5;
```

### Setting the order of results

Ordering results are set in order of priority, and can be defined multiple times for sub-ordering.

order_by_asc()

```php
$items = User::order_by_asc('name')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `name` ASC
```

order_by_desc()

```php
$items = User::order_by_desc('name')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `name` DESC
```

Combining two order types

```php
$items = User::order_by_desc('name')
    ->order_by_asc('id')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `name` DESC, `id` ASC
```

order_by_expr()

```php
$items = User::order_by_expr('name+0')
    ->find_many();
    // SELECT * FROM `user` ORDER BY name+0
```

### Clearing previous order declarations

If an order declaration is already made (e.g. from a filter or previous code) that you want to over-ride, you can clear it:

```php
$items = User::order_by_desc('name')
    ->order_by_clear() // Clears out the name order from above
    ->order_by_asc('id')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `id` ASC
```

### Clearing previous where and having declarations

If an where or having declaration is already made (e.g. from a filter or previous code) that you want to over-ride, you can clear it:

For where:

```php
$items = User::where('name', 'Fred')
    ->clear_where() // Clears out all where declarations
    ->where('name', 'Joe')
    ->find_many();
    // SELECT * FROM `user` WHERE `name` = 'Joe'
```

Similarly for having:

```php
$items = User::group_by('name')
    ->having('name', 'Fred')
    ->clear_having() // Clears out all having declarations
    ->having('name', 'Joe')
    ->find_one();
    // SELECT * FROM `user` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1
```

If you only want to remove a single where that was previously set, you can remove it:

```php
$items = User::where('name', 'Fred')
    ->where('age', 10)
    ->remove_where('name')
    ->find_many();
    // SELECT * FROM `user` WHERE `age` = 10
```

### Getting all fields when previously selected fields

If a situation where a field to select is already specified, and you want all fields, just select('*') and the `*` goes to the front of the list:

```php
$items = User::select('name')
    ->select('*')
    ->find_one();
    // SELECT *, `name` FROM `user` LIMIT 1
```

For some databases (e.g. Mysql) the `*` must be at the start of the list

### Get raw SELECT query

Sometimes you may want to build a raw SELECT query for use, e.g. to send to a reporting module that directly connects to the database.
Instead of calling `find_many()` call `get_select_query()` and it will give you the raw SELECT ready to send to the database server.

## Default filtering

In some cases a default filter is very useful.
For example an `is_deleted` field that flags a record as deleted in the database but the fields are never returned in queries.
To set up default filtering, create a function in the model. For example:

```php
class Car extends Model
{
    public static function _defaultFilter($query) {
        return $query->where('car.is_deleted', 0);
    }
}
```

Any queries that attempt to load results from the car table will filter based on the `is_deleted` column.
It's recommended to include the table name in the default filter as it will be needed for any joins.

Don't forget to create an index on columns that have a default filter!

To override the default filtering, use the `clear_where()` function, for example:

```php
$count = Car::clear_where()->count();
// Gets the number of all cars, deleted or not
$count = Car::count();
// Gets only the cars that are not deleted
```

## First and Last items in a result

When using `foreach` to iterate through a list of results, there are two functions you can use to determine if the result is the first or last item.
This is very handy when outputting data and you want the first or last to be slightly different from the others.

```php
foreach ($items as $item) {
    if ($item->isFirstResult()) {
        // This is the first item in the list
    }
    if ($item->isLastResult()) {
        // This is the last item in the list
    }
}
```
