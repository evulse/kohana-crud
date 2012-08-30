CRUD Controller for Kohana v3.x
===========

This module provides a simple CRUD interface for the Kohana ORM. I personally use this when first building models to test relationships such as belongs_to and has_many.

It has bootstrap built in simply because as mentioned It's purpose is to run seperately to your application to allow you to modify data and respect any model functions that you may have triggered by the ->save() function


Dependecies
-----

This application relies on the use of

* Kohana ORM (Because this was designed around it)
* KOstache (Because I much prefer logic-less views)

Installing
-----

Simple add the module to your bootstrap file.

How to Use
-----

Simply go to /crud and it will list all active models. I don't think this controller needs any more instructions as it is purely just CRUD.