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

## Query mode

`Model::q()` starts a query for a model: conditions, ordering, and the finds build on it.

```php
$cars = Car::q()->where_gt('price', 1000)->find_many();
```

To add custom filters to a model, create a `QueryCar` class that extends `\Granada\Query`:

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

To create a row, use `Car::create($data)`. It returns a model containing `$data`, you need to `save()` to write to the database.

## Table-first queries

`Granada::for_table()` starts a query on a table without a model class.
It takes the table name and returns a query that chains conditions, ordering, and finds like any model query.

```php
$items = Granada::for_table('legacy_user')->where_gt('age', 21)->find_many();
// SELECT * FROM legacy_user WHERE age > '21';
```

This returns raw ORM object rows rather than models.

## By primary key

To load a model by its primary key, use the `find_one()` function, specifying the primary key:

```php
$item = User::q()->find_one($id);
// SELECT * FROM user WHERE id=$id;
echo $item->name;
```

## Loading a single record

To load a single record of a model, set the query parameters then use `find_one()` function to limit to one record:

```php
$item = User::q()->find_one();
// SELECT * FROM user LIMIT 1;
echo $item->name;
```

## Loading multiple records

Get a number of records from the database, in an iterable list, by using the `find_many()` function.

```php
$items = User::q()->find_many();
// SELECT * FROM user;
foreach ($items as $item) {
   echo $item->name;
}
```

## Loading multiple records, mapped to a structure

Get a number of records from the database, in a custom mapped structure, by using the `find_map()` function.

```php
$item = User::q()->find_map(fn ($e) => (object)[
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
foreach (User::q()->where('active', 1)->find_many_lazy() as $id => $user) {
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
foreach (User::q()->find_many_lazy(200) as $user) { ... }
```

A smaller chunk uses less memory and runs more queries. A bigger
chunk does the opposite. The results you get are identical.

### Eager loading

`with()` works as usual. The relationship query runs once per chunk,
not once per model:

```php
foreach (Car::q()->with('parts')->find_many_lazy() as $car) {
    echo $car->parts[0]->name;
}
```

### Loop only once

The method returns a generator. A generator cannot restart. To loop
again, call `find_many_lazy()` again. Each call runs the queries
again, so the data may have changed.

### Queries the method refuses

The method throws an exception when the query has one of these:

- `group_by()` - the method loads each row by its id, and a grouped
  row has no single id
- `raw_query()` - the method loads the rows by rebuilding the query,
  and it cannot rebuild raw SQL
- a `join()` plus a custom `select()` - the rows are loaded from the
  table alone, so the select may only name that table's columns

If you turned on `return_result_sets`, it does not apply here: the
method always returns a generator.

## Limiting results

Specify the number of results you want to load using the `limit()` and `offset()` functions:

```php
$items = User::q()->offset(15)
    ->limit(5)
    ->find_many();
// SELECT * FROM user LIMIT 5 OFFSET 15;
```

### Filtering results

Add comparative filters to the query by using various functions.
As you can see they are by default all reducing (uses AND).

```php
$items = User::q()->where('class', 'Test')
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
$items = User::q()->where_class('Test')
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
$items = User::q()->where_id_in(
    Invoice::q()->where_is_paid(false)->find_pairs('user_id', 'user_id')
)->find_many();
// SELECT user_id FROM invoice WHERE is_paid = 0
// SELECT * FROM user WHERE id IN (1,2,3,4,5,6,7,8,9,10)

// Do instead
$items = User::q()->where_id_in(
    Invoice::q()->where_is_paid(false)->select('user_id')
)->find_many();

// SELECT * FROM user WHERE id IN (SELECT user_id FROM invoice WHERE is_paid = 0)
```

The subquery's values bind into the outer statement as placeholders.

### Some built-in OR filters

To reduce complexity of the OR filtering (below) a few shortened filters are available to check whether a field is NULL as well.

For example:

```php
$items = User::q()->where_lt_or_null('age', 5)
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
$items = User::q()->where_any_is(
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
$items = User::q()->where_any_is(
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
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe'],
        ['name' => 'Fred', 'age' => 20],
    ])->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))
```

Overriding the comparison for one data type:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => 10],
        ['name' => 'Fred', 'age' => 20],
    ], array('age' => '>')
    )->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))
```

Overriding the comparison for all data types:

```php
$items = User::q()->where_any_is(
    [
        ['score' => '5', 'age' => 10],
        ['score' => '15', 'age' => 20],
    ], '>')->find_many();
    // SELECT * FROM `user` WHERE (( `score` > '5' AND `age` > '10' ) OR ( `score` > '15' AND `age` > '20' ))
```

You can use NULL values in comparisons:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => NULL],
        ['name' => NULL, 'age' => 20],
    ])->find_many();
// SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` IS NULL ) OR ( `name` IS NULL AND `age` = '20' ))
```

They also work with the `!=` operator:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => NULL],
        ['name' => NULL, 'age' => 20],
    ], '!=')
    ->find_many();
    // SELECT * FROM `user` WHERE (( `name` != 'Joe' AND `age` IS NOT NULL ) OR ( `name` IS NOT NULL AND `age` != '20' ))
```

Pass an array to convert it into an IN or NOT IN (depending on the operator):

```php
$items = User::q()->where_any_is(
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
$items = User::q()->where('class', 'Test')
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
$items = User::q()->where('enabled', 1)
    ->or_where(function (User $q) {
        $q->where_name('Jack')->where_verified(1);
    })
    ->find_many();
// SELECT * FROM `user` WHERE `enabled` = 1 OR ( `user`.`name` = 'Jack' AND `user`.`verified` = '1' )
```

Inside the closure, `$q` is a clean starting point for building a sub-condition.

A closure inside a closure nests the groups:

```php
$items = User::q()->where(function (User $q) {
    $q->where('enabled', 1)->where(function (User $inner) {
        $inner->where('role', 'admin')->or_where('role', 'owner');
    });
})->find_many();
// SELECT * FROM `user` WHERE `enabled` = 1 AND ( `role` = 'admin' OR `role` = 'owner' )
```

### NOT conditions

`where_not()` and `or_where_not()` prefix the sub-condition with NOT:

```php
$items = User::q()->where_not('role', 'banned')->find_many();
// SELECT * FROM `user` WHERE NOT ( `role` = 'banned' )

$items = User::q()->where_not(function (User $q) {
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
$items = User::q()->where_exists(
    Invoice::q()->where_raw('invoice.user_id = user.id')->where('is_paid', 0)
)->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT * FROM `invoice` WHERE invoice.user_id = user.id AND `is_paid` = 0 )
```

HAVING has the same methods: `having()` takes a closure, `or_having()`, `having_not()` and `or_having_not()` are the HAVING forms of `or_where()`, `where_not()` and `or_where_not()`, and `having_exists()` / `having_not_exists()` render `EXISTS` in HAVING.

### Setting the order of results

Ordering results are set in order of priority, and can be defined multiple times for sub-ordering.

order_by_asc()

```php
$items = User::q()->order_by_asc('name')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `name` ASC
```

order_by_desc()

```php
$items = User::q()->order_by_desc('name')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `name` DESC
```

Combining two order types

```php
$items = User::q()->order_by_desc('name')
    ->order_by_asc('id')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `name` DESC, `id` ASC
```

order_by_expr()

```php
$items = User::q()->order_by_expr('name+0')
    ->find_many();
    // SELECT * FROM `user` ORDER BY name+0
```

### Clearing previous order declarations

If an order declaration is already made (e.g. from a filter or previous code) that you want to over-ride, you can clear it:

```php
$items = User::q()->order_by_desc('name')
    ->order_by_clear() // Clears out the name order from above
    ->order_by_asc('id')
    ->find_many();
    // SELECT * FROM `user` ORDER BY `id` ASC
```

### Clearing previous where and having declarations

If an where or having declaration is already made (e.g. from a filter or previous code) that you want to over-ride, you can clear it:

For where:

```php
$items = User::q()->where('name', 'Fred')
    ->clear_where() // Clears out all where declarations
    ->where('name', 'Joe')
    ->find_many();
    // SELECT * FROM `user` WHERE `name` = 'Joe'
```

Similarly for having:

```php
$items = User::q()->group_by('name')
    ->having('name', 'Fred')
    ->clear_having() // Clears out all having declarations
    ->having('name', 'Joe')
    ->find_one();
    // SELECT * FROM `user` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1
```

If you only want to remove a single where that was previously set, you can remove it:

```php
$items = User::q()->where('name', 'Fred')
    ->where('age', 10)
    ->remove_where('name')
    ->find_many();
    // SELECT * FROM `user` WHERE `age` = 10
```

### Getting all fields when previously selected fields

If a situation where a field to select is already specified, and you want all fields, just select('_') and the `_` goes to the front of the list:

```php
$items = User::q()->select('name')
    ->select('*')
    ->find_one();
    // SELECT *, `name` FROM `user` LIMIT 1
```

For some databases (e.g. Mysql) the `*` must be at the start of the list

### Get raw SELECT query

Sometimes you may want to build a raw SELECT query for use, e.g. to send to a reporting module that directly connects to the database.
Instead of calling `find_many()` call `get_select_query()` and it will give you the raw SELECT ready to send to the database server.

## Default filtering

A query class applies a default filter, so every query for the model starts from the same conditions.
For example, hide every car that is not enabled, without adding the condition to each query.
To flag records as deleted, use the soft deletes below - those rows are already hidden.
Add your own conditions by overriding `defaultFilter()` on the model's query class:

```php
class QueryCar extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('car.enabled', 1);
    }
}
```

Every query for that model starts with the conditions in the default filter.
Soft-deleted rows are hidden before `defaultFilter()` runs, so the filter adds only its own conditions.
It's recommended to include the table name in the conditions as it will be needed for any joins.

Don't forget to create an index on columns that have a default filter!

To override the default filtering, use the `clear_where()` function, for example:

```php
$count = Car::q()->clear_where()->count();
// Gets the number of all cars, filtered or not
$count = Car::q()->count();
// Gets only the cars the default filter lets through
```

## Soft deletes

Setting `$_soft_delete_column` on a model makes `delete()` soft-delete the row instead of removing it.
Soft-deleted rows drop out of finds, counts, and relationship queries for that model.

```php
class Car extends Model
{
    protected static ?string $_soft_delete_column = 'is_deleted';
}
```

`delete()` soft-deletes the row.
`restore()` brings it back.
`delete_forced()` removes the row, on any model.

```php
$car = Car::q()->find_one(1);
$car->delete();        // soft-deleted, queries no longer return it
$car->restore();       // queries return it again
$car->delete_forced(); // removes the row for good
```

`delete_many()` soft-deletes every matching row on a soft-delete model, and removes rows as before on every other model.

```php
Car::q()->where('enabled', 0)->delete_many();
// Soft-deletes all disabled cars
```

To list the soft-deleted rows, use `clear_where()`.

```php
$count = Car::q()->clear_where()->where('car.is_deleted', 1)->count();
// Gets the number of soft-deleted cars
```

## Timestamps

Setting `$_auto_timestamps` on a model makes Granada write its `created_at` and `updated_at` columns, in UTC, on every write.

```php
class Car extends Model
{
    protected static bool $_auto_timestamps = true;
}
```

An insert writes both columns.
An update writes `updated_at`.
A save with no other change writes neither.

```php
$car = Car::create(['name' => 'Car1']);
$car->save();          // created_at and updated_at are written

$car->name = 'Car2';
$car->save();          // updated_at is written, created_at is not

$car->save();          // no change, so updated_at is not written
```

Other writes to the table update `updated_at` as well, including increment(), update_many() and soft-deletes.

```php
$car->increment('views');   // updated_at is written with the count
```

The columns default to `created_at` and `updated_at`.
Set `$_auto_timestamp_columns` to name different columns.

```php
class Car extends Model
{
    protected static bool $_auto_timestamps = true;
    protected static array $_auto_timestamp_columns = [
        'created' => 'made_on',
        'updated' => 'changed_on',
    ];
}
```

If you set the `created_at` or `updated_at` field, then it saves your data rather than the current time.

```php
$car = Car::create([
    'name'       => 'Car1',
    'created_at' => '2020-01-01 00:00:00',
]);
$car->save();   // created_at is written as given, updated_at is written
```

`$_auto_timestamps_ignore` lists fields that won't update `updated_at` when changed.
Examples include `sort_order`.

```php
class Car extends Model
{
    protected static bool $_auto_timestamps = true;
    protected static array $_auto_timestamps_ignore = ['sort_order'];
}
```

## Junction writes

A `has_many_through` relationship loads its models through a link table. The same declaration gives you `attach()`, `detach()`, and `sync()` to write that table.

Name the link model and the two link columns when you declare the relationship:

```php
class User extends Model
{
    public function roles()
    {
        return $this->has_many_through(
            associated_class_name: 'Role',
            link_model: 'UserRole',
            this_key: 'user_id',
            other_key: 'role_id',
        );
    }
}
```

`attach()` links one model to another. It creates the link row if it is not there yet, and the data you pass is written onto the row, whether the link is new or already there.

```php
$user->attach_roles(2);                   // creates the user-role link
$user->attach_roles(2, ['enabled' => 1]); // adds data to the link
```

`detach()` removes the link to one model. Called with no argument, it removes every link this model has.

```php
$user->detach_roles(2); // removes that link
$user->detach_roles();  // removes every role link for this user
```

`sync()` sets the list to match what you send. Either send a list of ids, or a list of id => data to also set attributes on the link table.

```php
$user->sync_roles([1, 2, 3]);                        // links become exactly 1, 2, 3
$user->sync_roles([1 => ['enabled' => 1], 3 => []]); // 1 and 3, with data on 1
```

All three work on the relation itself, for a call that reads better chained:

```php
$user->roles()->attach(2, ['enabled' => 1]);
```

### Ordering the links

`ordered_by()` names a column on the link table that holds each link's position. Reads come back in that order, and the writes keep it up to date.

```php
public function steps()
{
    return $this->has_many_through(
        associated_class_name: 'Step',
        link_model: 'RecipeStep',
        this_key: 'recipe_id',
        other_key: 'step_id',
    )->ordered_by('position');
}
```

`attach()` adds a new link at the end, and `sync()` numbers the links in the order you list them.

```php
$recipe->attach_steps(3);      // placed last
$recipe->sync_steps([9, 4, 7]); // numbered 1, 2, 3 in the order given
```

`detach()` removes a link and leaves the other positions as they are, so the column can have gaps.

Writing the same parent's links from two processes at once can give two links the same position. Wrap the write in a mutex of your own if your application can do that. A model's `save()` never writes links.

## Syncing children

A `has_many` relationship loads a parent's child rows. `sync()` sets the children to the rows you send in one call, adding, updating, and deleting as needed. Send the child's id on a row to update it, or leave it off to create a new child.

```php
class EventEntry extends Model
{
    public function extras()
    {
        return $this->has_many('EntryExtra', 'entry_id');
    }
}

$entry->sync_extras([
    ['id' => 41, 'qty' => 3, 'options' => '{"tyres":4}'], // updates child 41
    ['qty' => 1, 'options' => '...'],                    // creates a child
]);
```

An id that doesn't match any of the parent's children is simply ignored. If the child model uses soft deletes, children you leave out aren't really gone - they're just hidden, and they come back the moment you list them again.

The foreign key is set from the parent, so you never set it yourself. Every write runs inside one transaction, and the call does nothing until the parent has been saved.

`ordered_by()` names a column on the child table that holds each row's position, the same way it does on a link table. `sync()` numbers the rows in the order you list them, and reads come back in that order.

```php
$entry->ordered_extras()->sync([['name' => 'A'], ['name' => 'B']]); // positions 1, 2
```

A model's `save()` never writes children; the call is always explicit.

## Existence filters and related counts

Filter rows by their related rows, or keep a related count on each row, without loading them.

### Filtering by related rows

`where_has()` keeps the rows that have at least one related row matching.
Pass the relationship method name.
The relationship is `has_one`, `has_many` or `belongs_to`.

```php
$users = User::q()->where_has('invoices')->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT 1 FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` )
```

Filter the related rows with an array of column => value pairs.
A null value becomes `IS NULL`.

```php
$users = User::q()->where_has('invoices', ['is_paid' => 0])->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT 1 FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` AND `is_paid` = '0' )
```

Or filter them with a closure.
It receives the related model's query, so type it as the related query class - `QueryInvoice` for the `Invoice` model - or as the base `Granada\Query`.

```php
$users = User::q()->where_has('invoices', function (QueryInvoice $query) {
    $query->where('is_paid', 0)->where_gt('amount', 100);
})->find_many();
```

`where_not_has()` keeps the rows with none.

```php
$users = User::q()->where_not_has('invoices')->find_many();
// SELECT * FROM `user` WHERE NOT EXISTS ( SELECT 1 FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` )
```

Naming the relationship in the method gives `where_has_{relation}()` and `where_not_has_{relation}()`.

```php
$users = User::q()->where_has_invoices()->find_many();
$users = User::q()->where_not_has_invoices(['is_paid' => 0])->find_many();
```

A row that matches appears once, whatever the number of related rows, so `count()`, `limit()` and `offset()` include it once.

### Keeping rows with at least N related rows

Pass `count` to keep only rows with at least that many matching related rows.

```php
$users = User::q()->where_has('invoices', count: 3)->find_many();
// SELECT * FROM `user` WHERE ( SELECT COUNT(*) FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` ) >= 3
```

### Keeping a related count on each row

`with_count()` gives each row the number of its related rows, on the model as `{relation}_count`.
The count is read in the one find query, so the related rows are never loaded.

```php
foreach (User::q()->with_count('invoices')->find_many() as $user) {
    echo $user->invoices_count; // the count, as an integer
}
// SELECT *, ( SELECT COUNT(*) FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` ) AS `invoices_count` FROM `user`
```

`with_exists()` puts a boolean on `{relation}_exists`.

```php
foreach (User::q()->with_exists('invoices')->find_many() as $user) {
    echo $user->invoices_exists ? 'has invoices' : 'none'; // a boolean
}
```

Both take the same array or closure as `where_has()` to filter what is counted.

```php
$users = User::q()->with_count('invoices', ['is_paid' => 0])->find_many();
```

The count is a set value on the model's relationships, so `clear_computed_values()` keeps it.

Existence filters and related counts cover `has_one`, `has_many` and `belongs_to`.
A `has_many_through` relationship is not supported yet and throws.

## Property Access Methods

When accessing a property on a model (e.g. `$model->property`), Granada checks several method prefixes in order to resolve the value.

### `get_` prefix - Transform an existing value

If the property exists in the database (not null), and a `get_{property}` method exists, the raw database value is passed to the method and the return value is used. This is recalculated every time the property is accessed.

```php
class Car extends Model {
    public function get_brand($value) {
        return ucfirst(strtolower($value));
    }
}

$car = Car::q()->find_one(1);
echo $car->brand; // e.g. Toyota
```

### `missing_` prefix - Compute when not in the database

If the property does not exist in the database (null), and a `missing_{property}` method exists, the method is called with no arguments. The result is **recalculated every time** the property is accessed.

```php
class Car extends Model {
    public function missing_nameNow() {
        return $this->name . '-' . microtime(true);
    }
}

$car = Car::q()->find_one(1);
echo $car->nameNow; // includes current time
sleep(1);
echo $car->nameNow; // will be different
```

Use `missing_` for lightweight computations that should reflect the current state of the model on every access.

### `missingonce_` prefix - Compute once and keep

If the property does not exist in the database (null), and a `missingonce_{property}` method exists, the method is called on the **first access only**. The result is kept and returned on all subsequent accesses within the same object lifecycle.

This is ideal for expensive operations such as database queries that you do not expect to change within the current request.

```php
class User extends Model {
    public function missingonce_orderCount() {
        return Order::q()->where('user_id', $this->id)->count();
    }
}

$user = User::q()->find_one(1);
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

$car = Car::q()->find_one(1);
echo $car->manufactor->name; // Lazy-loaded on first access
echo $car->manufactor->name; // No database query
```

See the Relationships section below for more detail.

### `clear_computed_values()` - Work out computed values again

Lazy-loaded relationships and `missingonce_` values are kept in the model's `$relationships` array. `clear_computed_values()` drops those kept values, so the next read of each affected property works them out again.

```php
$user = User::q()->find_one(1);
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

## Increment and decrement

`increment()` adds an amount to a column. `decrement()` subtracts one.
They only affect that column, and let you run two increments or decrements without losing count.

```php
$widget->increment('views');               // UPDATE widget SET views = views + 1
$widget->decrement('stock', 2);            // UPDATE widget SET stock = stock - 2

$new_total = $widget->increment('views');  // reads the value back from the row
```

`$new_total` and `$widget->views` store the new value.
On a newly created model, the database isn't updated until you `save()`.
If something goes wrong, they return `false`.

## Mass updates

`update_many()` writes the same values to every row the query matches, in one UPDATE, without loading any rows.

```php
Car::q()
    ->where('enabled', 0)
    ->update_many(['name' => 'Sold']);
```

It returns `true` when the statement runs and `false` when the write fails.
Call it with an empty array and nothing is written.

The query's where conditions pick the rows.
A query with no where conditions updates every row in the table.

```php
Part::q()->update_many(['price' => 0]);
// Updates every part
```

A query with a join throws.
A soft-delete model's hidden rows stay hidden; `clear_where()` lifts the hiding.

An `Expression` value writes raw SQL, for shifts like `sort_order + 1`.

```php
use Granada\Orm\Expression;

Part::q()
    ->where('name', 'Wheel')
    ->update_many([
        'price' => new Expression('price * 1.1'),
    ]);
```

Every other value runs through the model's `set_<column>()` method, so a date or money object reaches the database converted.
