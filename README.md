Dynamic menu user profile field for moodle.
Now users can create user menu fields whose values are retrieved from the moodle DB.
Basically, the user can set a sql query as value definition of the field. Please note that the query *must* return two fields: id and data.
Please note that this is an advanced plugin, mainly intended for developers and very advanced moodle users. You must understand how moodle DB and sql language work in order to use this plugin properly.

Installation instructions:
Just upload and install it like any other Moodle plugin.

Placeholders in SQL-Queries
[fullname] - replaced with fullname
[profile_field...] - replaced with custom profile field
[anyother] - replaced with anyother profile field

Supported versions:
3.11 onwards (hopefully)
