yii2-crud
=========

Provides:

* base ActiveRecord class with behaviors
* base ActiveQuery class for advanced search
* model generator
* ActiveController and a set of Action classes providing a RESTful service with a default HTML format
* default views mechanism

## ActiveRecord

### Filtering

Filters are separated from validator rules. This allows to perform filtering and validation separately.
This is sometimes required when filters modify values in such way they can't be applied twice.

This is implemented in the `netis\crud\db\FilterAttributeValuesTrait` trait.
The base AR class also introduces two new events, _beforeFilter_ and _afterFilter_.

### Relations

Since the CRUD renders all model relations, they need to be enumerated in the model.
This is done in the new `relations()` method.

Saving relations is done by using the `netis\crud\db\LinkableBehavior` behavior.

Relations are also used for authorization. The base AR class has the `netis\rbac\AuthorizerBehavior` attached.

### Labels

Models can be cast to string, because the base AR class implements the `__toString()` method.
By attaching the `netis\crud\db\LabelsBehavior` behavior, you can select attributes used to generate
a string representation of a specific model.
The behavior also allows to define general label for a model and its relations.

### Attribute formats

Formatter formats can be assigned to model attributes in the `attributeFormats()` method.
Defaults are detected based on the database column types.

## ActiveController

### Response formats

The default response format is HTML. Other supported formats include JSON and XML.

When an action returns a large collection, streaming is used to output data.
This is slower, but allows to send an extremely large response and renders and output data at the same time.
Thanks to this, paging is not necessary to export whole contents of the database tables.

New formats can be easily added, but this requires providing both a renderer stream
and a response formatter classes.

### Default views

Defaults views are provided for the HTML response format. They support overriding in the same fashion
as in themes.

### Form builder

For the update action's form, the fields are automatically generated based on model's attributes, relations
and their formats.

### Relations

The view and update actions display all model relations either as single values (_hasOne_) or grids (_hasMany_).
In the update action, for both relation types, new or existing records can be associated
with the model being updated.

### Navigation

A context menu is available.

