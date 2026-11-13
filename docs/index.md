# GranadaORM

## Documentation

Most of the query language comes from Idiorm and Paris, so start with their docs
for anything this page does not cover. The sections here explain the rest, with
an example for each.

The comments in the examples give the SQL that Granada sends. Granada escapes
data for you, except in a raw query.

## Query mode

Query mode starts at `Model::q()`. You add conditions and ordering, then one of
the finds executes the query:

```php
$cars = Car::q()->where_gt('price', 1000)->find_many();
```

To add a custom query action to a model, create a `QueryCar` class that extends
`\Granada\Query`:

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

To create a model, use `Car::create($data)`. It returns the model unsaved, with
the data you passed, and `save()` writes it to the database.

## Table-first queries

A table-first query starts at `Granada::for_table()` on a table that has no model
class. You add conditions and ordering, then call a find, as with a model query:

```php
$items = Granada::for_table('legacy_user')->where_gt('age', 21)->find_many();
// SELECT * FROM legacy_user WHERE age > '21';
```

Its finds return ORM rows rather than models, so the model's methods and
relationships are not available.

## By primary key

Pass the key to `find_one()` to load one model by its primary key:

```php
$item = User::q()->find_one($id);
// SELECT * FROM user WHERE id=$id;
echo $item->name;
```

## Loading a single record

Set your conditions, then call `find_one()` for the first match:

```php
$item = User::q()->where('name', 'John')->find_one();
// SELECT * FROM user WHERE name = 'John' LIMIT 1;
echo $item->name;
```

## Loading multiple records

`find_many()` returns the result set, which you can loop over:

```php
$items = User::q()->find_many();
// SELECT * FROM user;
foreach ($items as $item) {
    echo $item->name;
}
```

## Loading multiple records, mapped to a structure

`find_map()` calls your function on each model and collects the results, so you
get your own structure instead of models:

```php
$items = User::q()->find_map(fn ($e) => (object)[
    'id'      => $e->id,
    'name'    => $e->first_name . ' ' . $e->last_name,
    'isChild' => $e->age < 18,
]);

foreach ($items as $item) {
    echo $item->isChild;
}
```

## Loading many records one at a time

A lazy find yields models one at a time as a generator, so it suits a result too
large to keep in memory, such as a report over thousands of rows. `find_many_lazy()`
returns one:

```php
foreach (User::q()->where('active', 1)->find_many_lazy() as $id => $user) {
    echo $user->name;
}
```

The order and array keys match `find_many()`.

### The chunk size

`find_many_lazy()` reads the rows in chunks. Pass how many each query should
fetch:

```php
// 200 rows per query
foreach (User::q()->find_many_lazy(200) as $user) { ... }
```

A smaller chunk uses less memory and runs more queries; a bigger chunk uses more
memory and runs fewer queries. The results are identical either way.

### Eager loading

`with()` works as usual, and the relationship query runs once per chunk rather
than once per model:

```php
foreach (Car::q()->with('parts')->find_many_lazy() as $car) {
    echo $car->parts[0]->name;
}
```

### Run the find once

A lazy find is single-use: to run it again, call `find_many_lazy()` again. Each
call runs the queries again, so the data may have changed.

### Queries the method rejects

The method throws when the query has one of these:

- `group_by()`, since a grouped row has no single id
- `raw_query()`
- a `join()` plus a custom `select()`, since the select may only name the table's
  own columns

`return_result_sets` does not apply here: the method always returns a generator.
For small results, use `find_many()`.

## Limiting results

Use `limit()` and `offset()` to select a page of results:

```php
$items = User::q()->offset(15)
    ->limit(5)
    ->find_many();
// SELECT * FROM user LIMIT 5 OFFSET 15;
```

### Filtering results

Add comparative filters to the query. They combine with AND, so each one narrows
the results:

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

A suffix method puts the column in the method name: `where_class('Test')` does the
same as `where('class', 'Test')`, and the calls below match the list above:

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

Your IDE cannot detect the suffix methods that Granada adds at runtime, so list
the ones you use in a `@method` docblock on the query class:

```php
/**
 * @method QueryCar where_enabled($value)
 * @method QueryCar where_enabled_not_equal($value)
 * @method QueryCar where_enabled_like($value)
 * @method QueryCar where_enabled_not_like($value)
 * @method QueryCar where_enabled_gt($value)
 * @method QueryCar where_enabled_lt($value)
 * @method QueryCar where_enabled_gte($value)
 * @method QueryCar where_enabled_lte($value)
 */
```

### Subselects for in and not in

Pass a query where you would pass an array, and Granada runs it as a subselect, so
one query replaces two:

```php
$items = User::q()->where_id_in(
    Invoice::q()->where_is_paid(false)->select('user_id')
)->find_many();

// SELECT * FROM user WHERE id IN (SELECT user_id FROM invoice WHERE is_paid = 0)
```

### OR filters that match NULL

These filters match a range or a list and also match NULL, so a row with no value
still matches:

```php
$items = User::q()->where_lt_or_null('age', 5)
    ->where_gt_or_null('age', 10)
    ->where_gte_or_null('friends', 2)
    ->where_lte_or_null('friends', 4)
    ->where_not_in_or_null('age', [3, 4, 5])
    ->find_many();
```

### Using OR in filters

Conditions combine with AND, so `where_any_is()` takes a list of groups and ORs them
together. The query below matches either name, as long as the user is
enabled:

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

A second argument to `where_any_is()` changes the operator for every comparison:

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

One group can contain more than one comparison, and the comparisons in a group
combine with AND:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe'],
        ['name' => 'Fred', 'age' => 20],
    ])->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' ) OR ( `name` = 'Fred' AND `age` = '20' ))
```

Send an array of column => operator to change the operator for those columns only,
or a string to change it for every comparison:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => 10],
        ['name' => 'Fred', 'age' => 20],
    ], ['age' => '>'])
    ->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` > '10' ) OR ( `name` = 'Fred' AND `age` > '20' ))

$items = User::q()->where_any_is(
    [
        ['score' => '5', 'age' => 10],
        ['score' => '15', 'age' => 20],
    ], '>')->find_many();
    // SELECT * FROM `user` WHERE (( `score` > '5' AND `age` > '10' ) OR ( `score` > '15' AND `age` > '20' ))
```

A `null` value becomes `IS NULL`:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => null],
        ['name' => null, 'age' => 20],
    ])->find_many();
// SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` IS NULL ) OR ( `name` IS NULL AND `age` = '20' ))
```

With the `!=` operator it becomes `IS NOT NULL`:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => null],
        ['name' => null, 'age' => 20],
    ], '!=')
    ->find_many();
    // SELECT * FROM `user` WHERE (( `name` != 'Joe' AND `age` IS NOT NULL ) OR ( `name` IS NOT NULL AND `age` != '20' ))
```

An array value becomes `IN`, or `NOT IN` with the `!=` operator:

```php
$items = User::q()->where_any_is(
    [
        ['name' => 'Joe', 'age' => [18, 19]],
        ['name' => ['Bob', 'Jack'], 'age' => 20],
    ], ['age' => '!='])
    ->find_many();
    // SELECT * FROM `user` WHERE (( `name` = 'Joe' AND `age` NOT IN ('18', '19') ) OR ( `name` IN ('Bob', 'Jack') AND `age` != '20' ))
```

### Adding a condition when it applies

`onlyif()` makes a long chain readable when part of it depends on a value. It adds
the closure's conditions when the condition is true and skips them when it is
false. The closure can add ordering too:

```php
$min_age = 5;
$order = true;
$items = User::q()->where('class', 'Test')
    ->onlyif(false, function (QueryUser $q) { // skipped
        $q->where_lt('age', 10);
    })
    ->onlyif($min_age > 0, function (QueryUser $q) use ($min_age) {
        $q->where_gt('age', $min_age);
    })
    ->onlyif($order, function (QueryUser $q) {
        $q->order_by_asc('age');
    })
    ->find_many();
// SELECT * FROM `user` WHERE `class` = 'Test' AND `age` > '5' ORDER BY `age` ASC
```

### Grouping conditions

A group condition puts a closure's conditions in parentheses, which is how you
combine OR logic with an AND query. Inside the closure, `$q` is a query for the
same model, so type it as the model's query class:

```php
$items = User::q()->where('enabled', 1)
    ->or_where(function (QueryUser $q) {
        $q->where_name('Jack')->where_verified(1);
    })
    ->find_many();
// SELECT * FROM `user` WHERE `enabled` = 1 OR ( `user`.`name` = 'Jack' AND `user`.`verified` = '1' )
```

Group conditions can contain other groups:

```php
$items = User::q()->where(function (QueryUser $q) {
    $q->where('enabled', 1)->where(function (QueryUser $inner) {
        $inner->where('role', 'admin')->or_where('role', 'owner');
    });
})->find_many();
// SELECT * FROM `user` WHERE `enabled` = 1 AND ( `role` = 'admin' OR `role` = 'owner' )
```

### NOT conditions

`where_not()` and `or_where_not()` negate a condition:

```php
$items = User::q()->where_not('role', 'banned')->find_many();
// SELECT * FROM `user` WHERE NOT ( `role` = 'banned' )

$items = User::q()->where_not(function (QueryUser $q) {
    $q->where('role', 'banned')->where('enabled', 0);
})->find_many();
// SELECT * FROM `user` WHERE NOT ( `role` = 'banned' AND `enabled` = 0 )
```

Every `where_*` method has an `or_where_*` form, so `or_where_price_gt(10)` adds
`OR price > 10`. It connects to the condition before it, so the OR only affects
that condition and your default filter keeps applying.

### EXISTS conditions

`where_exists()` selects the rows that a subquery returns, and
`where_not_exists()` is its negation:

```php
$items = User::q()->where_exists(
    Invoice::q()->where_raw('invoice.user_id = user.id')->where('is_paid', 0)
)->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT * FROM `invoice` WHERE invoice.user_id = user.id AND `is_paid` = 0 )
```

HAVING has the same methods. `having()` takes a group closure, and `or_having()`,
`having_not()` and `or_having_not()` mirror their `where` equivalents.
`having_exists()` and `having_not_exists()` test in HAVING.

### Setting the order of results

Each `order_by_*()` call adds to the order, so a second call orders rows that the
first call leaves equal:

```php
$items = User::q()->order_by_asc('name')->find_many();
// SELECT * FROM `user` ORDER BY `name` ASC

$items = User::q()->order_by_desc('name')->find_many();
// SELECT * FROM `user` ORDER BY `name` DESC

$items = User::q()->order_by_desc('name')
    ->order_by_asc('id')
    ->find_many();
// SELECT * FROM `user` ORDER BY `name` DESC, `id` ASC
```

`order_by_expr()` orders by SQL you write yourself:

```php
$items = User::q()->order_by_expr('name+0')
    ->find_many();
// SELECT * FROM `user` ORDER BY name+0
```

### Clearing the order

`order_by_clear()` removes the order set so far, including any order that a filter
added, so you can set the order again:

```php
$items = User::q()->order_by_desc('name')
    ->order_by_clear()
    ->order_by_asc('id')
    ->find_many();
// SELECT * FROM `user` ORDER BY `id` ASC
```

### Clearing where and having conditions

`clear_where()` removes every where condition, and `clear_having()` removes every
having condition, including conditions that a filter added:

```php
$items = User::q()->where('name', 'Fred')
    ->clear_where()
    ->where('name', 'Joe')
    ->find_many();
// SELECT * FROM `user` WHERE `name` = 'Joe'

$items = User::q()->group_by('name')
    ->having('name', 'Fred')
    ->clear_having()
    ->having('name', 'Joe')
    ->find_one();
// SELECT * FROM `user` GROUP BY `name` HAVING `name` = 'Joe' LIMIT 1
```

`remove_where()` removes the conditions on one column and leaves the rest:

```php
$items = User::q()->where('name', 'Fred')
    ->where('age', 10)
    ->remove_where('name')
    ->find_many();
// SELECT * FROM `user` WHERE `age` = 10
```

### Selecting all fields

`select('*')` selects every column, and Granada places it at the front of the
list:

```php
$items = User::q()->select('name')
    ->select('*')
    ->find_one();
// SELECT *, `name` FROM `user` LIMIT 1
```

MySQL needs the `*` at the start of the list, which is where Granada places it.

### Getting the SQL without running it

Call `get_select_query()` instead of `find_many()` to get the SQL without running
it, for example to pass to a reporting tool that connects to the database itself:

```php
$sql = User::q()->where('name', 'John')->get_select_query();
// SELECT * FROM `user` WHERE `name` = 'John'
```

The result is an inlined query, with its values filled in, so it runs as written.

## Default filtering

A query class can apply a default filter, so every query for the model starts
from the same conditions. This hides every car that is not enabled, without
adding the condition to each query. To mark records as deleted, use the soft
deletes below, which already hide those rows:

```php
class QueryCar extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('car.enabled', 1);
    }
}
```

Every model query starts with its default filter. The filter adds only its own
conditions, so soft deletes continue to work alongside it. Index the columns it
filters on.

To query without the filter, call `clear_where()`:

```php
$count = Car::q()->clear_where()->count();
// every car, filtered or not
$count = Car::q()->count();
// only the cars the default filter includes
```

## Soft deletes

A model opts into soft deletes by naming `$_soft_delete_column`. Its `delete()` then
marks the row instead of removing it, and marked rows are left out of finds,
counts, and relationship queries for that model:

```php
class Car extends Model
{
    protected static ?string $_soft_delete_column = 'is_deleted';
}
```

`delete()` soft-deletes the row, `restore()` returns it to normal finds, and
`delete_forced()` force-deletes it:

```php
$car = Car::q()->find_one(1);
$car->delete();        // soft-deleted, queries no longer return it
$car->restore();       // queries return it again
$car->delete_forced(); // removes the row permanently
```

`delete_many()` soft-deletes every matching row on a soft-delete model, and
removes rows on every other model:

```php
Car::q()->where('enabled', 0)->delete_many();
// Soft-deletes all disabled cars
```

To find soft-deleted rows, clear the filter with `clear_where()` and filter on the
column:

```php
$count = Car::q()->clear_where()->where('car.is_deleted', 1)->count();
// Returns the number of soft-deleted cars
```

## Automatic timestamps

A model opts into automatic timestamps by naming `$_auto_timestamps`. Granada then
writes its `created_at` and `updated_at` columns, in UTC, on every write:

```php
class Car extends Model
{
    protected static bool $_auto_timestamps = true;
}
```

An insert writes both columns, an update writes `updated_at`, and a save that
changes nothing writes neither:

```php
$car = Car::create(['name' => 'Car1']);
$car->save();          // created_at and updated_at are written

$car->name = 'Car2';
$car->save();          // updated_at is written, created_at is not

$car->save();          // no change, so neither column is written
```

Other writes to the table also update `updated_at`, including `increment()`,
`update_many()` and soft deletes:

```php
$car->increment('views');   // updated_at is written with the count
```

The columns are `created_at` and `updated_at` unless `$_auto_timestamp_columns`
names others:

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

A value you set yourself takes precedence over the current time, so seeding an old
date works:

```php
$car = Car::create([
    'name'       => 'Car1',
    'created_at' => '2020-01-01 00:00:00',
]);
$car->save();   // created_at is written as given, updated_at is written
```

`$_auto_timestamps_ignore` lists the fields that do not update `updated_at` when
they change, such as `sort_order`:

```php
class Car extends Model
{
    protected static bool $_auto_timestamps = true;
    protected static array $_auto_timestamps_ignore = ['sort_order'];
}
```

## Auto order

An auto-order column gives a model's rows a position. Name it with
`$_auto_order_column`:

```php
class Car extends Model
{
    protected static ?string $_auto_order_column = 'sort_order';
}
```

When a save writes 0 to the column, Granada stores the next number instead:

```php
$car = Car::create(['name' => 'Car1', 'sort_order' => 0]);
$car->save();   // sort_order is 1

$car->sort_order = 0;
$car->save();   // sort_order is 2
```

Any other value is saved as given:

```php
$car->sort_order = 7;
$car->save();   // sort_order is 7
```

The numbering covers the whole table, and it does not change `updated_at`. Two
concurrent writes can store the same number.

`set_order_many()` sets the listed rows to positions 1..n in the order of their
ids:

```php
Car::q()->set_order_many([5, 2, 9]);
```

## Junction writes

A `has_many_through` relationship loads its models through a link table. The
same declaration provides the junction writes `attach()`, `detach()` and `sync()`.
A relationship method is named `related_` plus the relationship's name, and
application code names the relationship without the prefix.
In the declaration, name the model for the link table and its two key columns:

```php
class User extends Model
{
    public function related_roles()
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

`attach()` adds a role to the user. If the user already has it, Granada stores the
values you pass with that role for this user:

```php
$user->attach_roles(2);                   // adds role 2
$user->attach_roles(2, ['enabled' => 1]); // adds role 2, with values
```

`detach()` removes a role from the user. With no argument it removes every role
the user has:

```php
$user->detach_roles(2); // removes role 2
$user->detach_roles();  // removes every role for this user
```

`sync()` makes the roles match what you send. Send ids, or ids with values to
store with each role:

```php
$user->sync_roles([1, 2, 3]);                        // roles become exactly 1, 2, 3
$user->sync_roles([1 => ['enabled' => 1], 3 => []]); // 1 and 3, with values on 1
```

All three also work on the relationship itself:

```php
$user->roles()->attach(2, ['enabled' => 1]);
```

### Ordering the related models

`ordered_by()` names the order column on the link table. Reads return in that
order:

```php
public function related_steps()
{
    return $this->has_many_through(
        associated_class_name: 'Step',
        link_model: 'RecipeStep',
        this_key: 'recipe_id',
        other_key: 'step_id',
    )->ordered_by('position');
}
```

`attach()` adds a new step at the end, and `sync()` numbers the steps in the order
you list them:

```php
$recipe->attach_steps(3);      // placed last
$recipe->sync_steps([9, 4, 7]); // numbered 1, 2, 3 in the order given
```

`detach()` removes a step and does not change the other positions, so the order
column can have gaps. A model's `save()` writes only its own row; use
`attach()`, `detach()` and `sync()` for link rows.

## Syncing children

A `has_many` relationship loads a parent's children, and `sync()` makes them match
the rows you send in one call. Send an entry with the child's id to update it, or
without one to create it:

```php
class EventEntry extends Model
{
    public function related_extras()
    {
        return $this->has_many('EntryExtra', 'entry_id');
    }
}

$entry->sync_extras([
    ['id' => 41, 'qty' => 3, 'options' => '{"tyres":4}'], // updates child 41
    ['qty' => 1, 'options' => '...'],                    // creates a child
]);
```

An id that matches none of the parent's children is ignored. On a child model with
soft deletes, a child you leave out is soft-deleted rather than removed, and it
appears again when you list it again.

Granada fills in the foreign key from the parent, so you do not set it yourself,
and every write runs inside one transaction. The call takes effect after the
parent is saved.

`ordered_by()` names the order column on the child table, just as it does on a
`has_many_through`. `sync()` numbers the rows in the order you list them, and reads
return in that order:

```php
$entry->ordered_extras()->sync([['name' => 'A'], ['name' => 'B']]); // positions 1, 2
```

A `save()` updates only the row; call `sync()` to update the children.

## Existence filters and related counts

Filter models by their related rows, or store a count of them on each model,
without loading the related rows.

### Filtering by related rows

An existence filter selects only the parents with at least one matching related
row. `where_has()` accepts the relationship's name, and the method must declare a
`has_one`, `has_many` or `belongs_to`:

```php
$users = User::q()->where_has('invoices')->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT 1 FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` )
```

Narrow it with an array of column => value pairs, where a null value becomes
`IS NULL`:

```php
$users = User::q()->where_has('invoices', ['is_paid' => 0])->find_many();
// SELECT * FROM `user` WHERE EXISTS ( SELECT 1 FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` AND `is_paid` = '0' )
```

A closure receives the related model's query, so type it as that model's query
class, `QueryInvoice` for `Invoice`, or as `Granada\Query`:

```php
$users = User::q()->where_has('invoices', function (QueryInvoice $query) {
    $query->where('is_paid', 0)->where_gt('amount', 100);
})->find_many();
```

`where_not_has()` is the negated form and selects the parents with none:

```php
$users = User::q()->where_not_has('invoices')->find_many();
// SELECT * FROM `user` WHERE NOT EXISTS ( SELECT 1 FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` )
```

Name the relationship in the method instead, and you get
`where_has_{relation}()` and `where_not_has_{relation}()`:

```php
$users = User::q()->where_has_invoices()->find_many();
$users = User::q()->where_not_has_invoices(['is_paid' => 0])->find_many();
```

A row that matches appears once, however many related rows it has, so `count()`,
`limit()` and `offset()` count it once.

### Selecting rows with at least N related rows

Pass `count` to select only the rows with at least that many matching related
rows:

```php
$users = User::q()->where_has('invoices', count: 3)->find_many();
// SELECT * FROM `user` WHERE ( SELECT COUNT(*) FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` ) >= 3
```

### Storing a related count on each model

`with_count()` stores a related count on each model as `{relation}_count`:

```php
foreach (User::q()->with_count('invoices')->find_many() as $user) {
    echo $user->invoices_count; // the count, as an integer
}
// SELECT *, ( SELECT COUNT(*) FROM `invoice` WHERE `invoice`.`user_id` = `user`.`id` ) AS `invoices_count` FROM `user`
```

`with_exists()` stores an existence flag on each model as `{relation}_exists`:

```php
foreach (User::q()->with_exists('invoices')->find_many() as $user) {
    echo $user->invoices_exists ? 'has invoices' : 'none';
}
```

Granada computes a related count or existence flag in the find query itself, so
it never loads the related rows. Both take the same array or closure as
`where_has()` to narrow the related rows:

```php
$users = User::q()->with_count('invoices', ['is_paid' => 0])->find_many();
```

Related counts and existence flags are set values, so
`clear_computed_values()` leaves them.

Existence filters and related counts cover `has_one`, `has_many` and
`belongs_to`. Using either with a `has_many_through` relationship throws.

## Property access methods

`$model->property` works like a column. When the column has no value, Granada
checks the model's methods in this order: `missing_`, `missingonce_`, then a
`related_` method named for the property. A `get_` method changes a column value
when it reads it. The sections below describe each form.

### `get_` transforms an existing value

When the column has a value and the model defines `get_{property}()`, Granada
passes the value in and uses the returned value:

```php
class Car extends Model
{
    public function get_brand($value)
    {
        return ucfirst(strtolower($value));
    }
}

$car = Car::q()->find_one(1);
echo $car->brand; // for example Toyota
```

The method runs on every read, so the property always reflects the stored value.

### `missing_` computes a value the database does not have

When no column contains the property and the model defines `missing_{property}()`,
Granada calls it on every read, so the value always reflects the current state:

```php
class Car extends Model
{
    public function missing_nameNow()
    {
        return $this->name . '-' . microtime(true);
    }
}

$car = Car::q()->find_one(1);
echo $car->nameNow; // includes the current time
sleep(1);
echo $car->nameNow; // a different value
```

### `missingonce_` computes a value once and stores it

A `missingonce_{property}()` method runs on the first read only, and every later
read returns the same value, which suits a slow call such as a query:

```php
class User extends Model
{
    public function missingonce_orderCount()
    {
        return Order::q()->where('user_id', $this->id)->count();
    }
}

$user = User::q()->find_one(1);
echo $user->orderCount; // queries the database
echo $user->orderCount; // returns the stored value, with no query
```

Do not define both a `missing_` and a `missingonce_` method for the same property.

### Relationships as properties

A relationship method is named `related_` plus the relationship's name, and it
returns the relationship. Granada loads the related model or models on the first
property read and stores them:

```php
class Car extends Model
{
    public function related_manufactor()
    {
        return $this->belongs_to('Manufactor');
    }
}

$car = Car::q()->find_one(1);
echo $car->manufactor->name; // lazy-loaded on first read
echo $car->manufactor->name; // no database query
```

A direct call uses the same bare name, so the prefix never appears in application
code:

```php
echo $car->manufactor()->sum('id');
```

### Clearing computed values

`clear_computed_values()` removes the computed values, so the next read computes
each one again:

```php
$user = User::q()->find_one(1);
echo $user->orderCount; // queries the database
$user->clear_computed_values();
echo $user->orderCount; // queries the database again
```

Granada preserves set values and eager-loaded results from `with()`.

## First and last row flags

The row flags `isFirstResult()` and `isLastResult()` report your position inside a
`foreach`. Use them to produce different output for the first or last row:

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

`ORM::transaction()` runs several writes as one transaction, so they commit
together or roll back together. The callable runs inside the transaction, Granada
returns its return value, and a thrown exception rolls the transaction back and
rethrows it:

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

Returning `false` is not a rollback: only a thrown exception rolls the transaction
back.

To run a transaction manually, use `beginTransaction()`, `commit()` and
`rollBack()`. The names match PDO, so code written against `get_db()` works
after you remove that prefix:

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

Every method above takes an optional connection name and uses the default
connection when you omit it:

```php
ORM::transaction(function () {
    // writes on the reporting connection
}, 'reporting');

ORM::beginTransaction('reporting');
```

If you start a transaction while one is already open, it joins that transaction.
Only the outermost transaction commits, so a failure anywhere rolls back the whole
transaction:

```php
ORM::transaction(function () {
    ORM::beginTransaction(); // joins the transaction already open
    // writes ...
    ORM::commit();           // ends this call only; nothing is committed yet
});
// the commit on the database happens here, with the outer call
```

`insert()` uses the same mechanism: every row you pass it is saved in one
transaction, so a failure on any row rolls back every row.

## Increment and decrement

`increment()` adds an amount to a column and `decrement()` subtracts an amount.
Each changes only its column, so two calls running at once do not lose a count:

```php
$widget->increment('views');               // UPDATE widget SET views = views + 1
$widget->decrement('stock', 2);            // UPDATE widget SET stock = stock - 2

$new_total = $widget->increment('views');  // reads the value back from the row
```

The model stores the new value. On a newly created model, `increment()` changes
only the model in memory; call `save()` to write it. If the write fails, both
methods return `false`.

## Mass updates

`update_many()` writes the same values to every row the query matches, in one
UPDATE, without loading any rows:

```php
Car::q()
    ->where('enabled', 0)
    ->update_many(['name' => 'Sold']);
```

It returns `true` when the write succeeds and `false` when it fails, and an empty
array writes nothing. The where conditions select the rows, so a query with no
where conditions updates every row in the table:

```php
Part::q()->update_many(['price' => 0]);
// Updates every part
```

A query with a join throws on both `update_many()` and `delete_many()`, and a
soft-delete model's hidden rows remain hidden until `clear_where()` clears the
filter. An `Expression` value writes raw SQL, for an adjustment such as
`sort_order + 1`.
Every other value converts through the model's `set_<column>()` method, so a date
or money object reaches the database converted:

```php
use Granada\Orm\Expression;

Part::q()
    ->where('name', 'Wheel')
    ->update_many([
        'price' => new Expression('price * 1.1'),
    ]);
```
