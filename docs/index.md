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

## Loading many records one at a time

Use `find_many_lazy()` when the result is too big to hold in memory,
for example a report over many thousands of rows. It returns a
generator: each step of the loop gives you one model:

```php
foreach (User::where('active', 1)->find_many_lazy() as $id => $user) {
    echo $user->name;
}
```

The order and the array keys are the same as `find_many()` gives
you.

### The chunk size

The method loads the rows in chunks. The first argument is the chunk
size. The default is 1000:

```php
// 200 rows per query
foreach (User::find_many_lazy(200) as $user) { ... }
```

A smaller chunk uses less memory and runs more queries. A bigger
chunk does the opposite. The results you get are identical.

### Eager loading

`with()` works as usual. The relationship query runs once per chunk,
not once per model:

```php
foreach (Car::with('parts')->find_many_lazy() as $car) {
    echo $car->parts[0]->name;
}
```

### Loop only once

The method returns a generator. A generator cannot restart. To loop
again, call `find_many_lazy()` again. Each call runs the queries
again, so the data may have changed.

### Queries the method refuses

The method throws an exception when the query has one of these:

- `group_by()` — the method loads each row by its id, and a grouped
  row has no single id
- `raw_query()` — the method loads the rows by rebuilding the query,
  and it cannot rebuild raw SQL
- a `join()` plus a custom `select()` — the rows are loaded from the
  table alone, so the select may only name that table's columns

If you turned on `return_result_sets`, it does not apply here: the
method always returns a generator.

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

The subquery's values bind into the outer statement as placeholders.

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
```

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
        ->onlyif(false, function(User $q) { // Will skip this filter
            $q->where_lt('age', 10);
        })
        ->onlyif($min_age > 0, function(User $q) use ($min_age) { // Will apply this filter only when min_age is greater than 0
            $q->where_gt('age', $min_age);
        })
        ->onlyif($order, function(User $q) {
            $q->order_by_asc('age');
        })
        ->find_many();
// SELECT * FROM `user` WHERE `class` = 'Test' AND `age` > '5' ORDER BY `age` ASC
```

### Grouping conditions

Some queries need to have sub-conditions grouped.
In SQL that means putting in parenthesis.
Particularly useful for OR logic when we also need an AND.
Pass a closure to group conditions together.
For example:

```php
$items = User::where('enabled', 1)
    ->or_where(function (User $q) {
        $q->where_name('Jack')->where_verified(1);
    })
    ->find_many();
// SELECT * FROM `user` WHERE `enabled` = 1 OR ( `user`.`name` = 'Jack' AND `user`.`verified` = '1' )
```

Inside the closure, `$q` is a clean starting point for building a sub-condition.

A closure can hold another closure, which nests the groups:

```php
$items = User::where(function (User $q) {
    $q->where('enabled', 1)->where(function (User $inner) {
        $inner->where('role', 'admin')->or_where('role', 'owner');
    });
})->find_many();
// SELECT * FROM `user` WHERE `enabled` = 1 AND ( `role` = 'admin' OR `role` = 'owner' )
```

### NOT conditions

`where_not()` and `or_where_not()` prefix the sub-condition with NOT:

```php
$items = User::where_not('role', 'banned')->find_many();
// SELECT * FROM `user` WHERE NOT ( `role` = 'banned' )

$items = User::where_not(function (User $q) {
    $q->where('role', 'banned')->where('enabled', 0);
})->find_many();
// SELECT * FROM `user` WHERE NOT ( `role` = 'banned' AND `enabled` = 0 )
```

A magic method exists so that wherever there was a `where_*` there is also an `or_where_*` so e.g. `or_where_price_gt(10)` adds `OR price > 10`.
The `or_where_*` condition connects to the condition before it, so an OR can not leak past an AND, and a default filter stays intact.

### EXISTS conditions

In sql, you can return just the rows that exist in a subquery.
Use `where_exists()` to declare the query that is used in the EXISTS portion. `where_not_exists()` outputs NOT EXISTS.

```php
$items = User::where_exists(
    Invoice::where_raw('invoice.user_id = user.id')->where('is_paid', 0)
)->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT * FROM `invoice` WHERE invoice.user_id = user.id AND `is_paid` = 0 )
```

HAVING has the same methods: `having()` takes a closure, `or_having()`, `having_not()` and `or_having_not()` are the HAVING forms of `or_where()`, `where_not()` and `or_where_not()`, and `having_exists()` / `having_not_exists()` render `EXISTS` in HAVING.

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

If a situation where a field to select is already specified, and you want all fields, just select('_') and the `_` goes to the front of the list:

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

## Property Access Methods

When accessing a property on a model (e.g. `$model->property`), Granada checks several method prefixes in order to resolve the value.

### `get_` prefix — Transform an existing value

If the property exists in the database (not null), and a `get_{property}` method exists, the raw database value is passed to the method and the return value is used. This is recalculated every time the property is accessed.

```php
class Car extends Model {
    public function get_brand($value) {
        return ucfirst(strtolower($value));
    }
}

$car = Model::factory('Car')->find_one(1);
echo $car->brand; // e.g. Toyota
```

### `missing_` prefix — Compute when not in the database

If the property does not exist in the database (null), and a `missing_{property}` method exists, the method is called with no arguments. The result is **recalculated every time** the property is accessed.

```php
class Car extends Model {
    public function missing_nameNow() {
        return $this->name . '-' . microtime(true);
    }
}

$car = Model::factory('Car')->find_one(1);
echo $car->nameNow; // includes current time
sleep(1);
echo $car->nameNow; // will be different
```

Use `missing_` for lightweight computations that should reflect the current state of the model on every access.

### `missingonce_` prefix — Compute once and keep

If the property does not exist in the database (null), and a `missingonce_{property}` method exists, the method is called on the **first access only**. The result is kept and returned on all subsequent accesses within the same object lifecycle.

This is ideal for expensive operations such as database queries that you do not expect to change within the current request.

```php
class User extends Model {
    public function missingonce_orderCount() {
        return Order::where('user_id', $this->id)->count();
    }
}

$user = Model::factory('User')->find_one(1);
echo $user->orderCount; // Queries the database
echo $user->orderCount; // Returns the kept value (no query)
```

Do not have both `missing_` and `missingonce_` methods exist for the same property. Order of priority may change.

### Relationships as properties

If a method matching the property name exists on the model and it returns a relationship (e.g. `has_one`, `has_many`, `belongs_to`), the related model(s) are lazy-loaded on first access and kept for subsequent access.

```php
class Car extends Model {
    public function manufactor() {
        return $this->belongs_to('Manufactor');
    }
}

$car = Model::factory('Car')->find_one(1);
echo $car->manufactor->name; // Lazy-loaded on first access
echo $car->manufactor->name; // No database query
```

See the Relationships section below for more detail.

### `clear_computed_values()` — Work out computed values again

Lazy-loaded relationships and `missingonce_` values are kept in the model's `$relationships` array. `clear_computed_values()` drops those kept values, so the next read of each affected property works them out again.

```php
$user = Model::factory('User')->find_one(1);
echo $user->orderCount; // Queries the database
$user->clear_computed_values();
echo $user->orderCount; // Queries the database again
```

Eager-loaded results (from `with()`), values routed to `$relationships` by `set()`, and values you write to `$relationships` yourself are kept.

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

## Transactions

Wrap several writes so they commit together or roll back together with `ORM::transaction()`. The callable runs inside a transaction. When it returns normally, the transaction is committed and its return value is passed through. When it throws, the transaction is rolled back and the exception is rethrown.

```php
$invoice = ORM::transaction(function () {
    $invoice = Invoice::create(['name' => 'Inv1']);
    $invoice->save();
    foreach ($lines as $line) {
        $row = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'name'       => $line,
        ]);
        $row->save();
    }

    return $invoice;
});
```

Returning `false` is not a rollback: only a thrown exception rolls the work back.

To run a transaction manually, use `beginTransaction()`, `commit()` and `rollBack()`. The names match PDO, so code written against `get_db()` moves over by dropping that prefix:

```php
ORM::beginTransaction();
try {
    // writes ...
    ORM::commit();
} catch (Throwable $e) {
    ORM::rollBack();
    throw $e;
}
```

Transactions live on one connection. Every method above takes an optional connection name, defaulting to the default connection, the same way `get_db()` does:

```php
ORM::transaction(function () {
    // writes on the reporting connection
}, 'reporting');

ORM::beginTransaction('reporting');
```

Transaction calls nest. A call made while a transaction is already open joins the outermost transaction: only the outermost begin and commit touch the database, and there is no savepoint to roll back to. A failure anywhere rolls the whole thing back:

```php
ORM::transaction(function () {
    ORM::beginTransaction(); // joins the transaction already open
    // writes ...
    ORM::commit();           // ends this call only; nothing is committed yet
});
// the commit on the database happens here, with the outer call
```

`insert()` builds on this: the rows you pass are saved one by one inside one transaction, so a failure on any row rolls back every row instead of leaving the transaction open.

## Concurrent writes

Granada has no row locks — there is no `SELECT ... FOR UPDATE`. Transactions plus atomic writes cover the work row locks usually do.

A transaction makes several writes succeed or fail together, but it does not stop two processes reading the same value and writing afterwards. This read-then-write shape races no matter what wraps it:

```php
// Two requests can read the same max and both save 431
$max = Quote::where('estimator_id', $id)->max('quote_number');
$quote->quote_number = $max + 1;
$quote->save();
```

Give each series its own counter row and bump it with one atomic write — a single UPDATE that adds to the stored value — instead of computing a number from the rows themselves. Two processes then never compute from the same starting number. Put a unique index on the number column as the backstop and retry the save when the index rejects a duplicate.

Check-then-act limits race the same way: reading a stock level, then writing the movement after payment, lets two buyers pass the same check. Do the check inside the write — one UPDATE that subtracts the amount and matches rows only where enough is left — and treat zero matched rows as out of stock.
