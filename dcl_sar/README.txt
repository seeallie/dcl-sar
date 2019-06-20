Introduction
------------

SAR, search and replace, allows searching and replaceing a
string against all text fields of node bundles via views. 


Dependencies
------------

SAR requires Views Bulk Operations module
https://www.drupal.org/project/views_bulk_operations


Installation
------------

Download the code and extract the "dcl_sar" folder into your modules/custom/.  

Enable the module and download its dependency module VBO via Drush: 
drush en dcl_sar -y 


Configuration
-------------

1. Go to 'content bulk edit' view. 
2. Add additional content types in the filter as needed. 
3. Save your changes. 

Usage 
------
Go to /content-bulk-edit/vbo-sar to use the feature. 

Features
-------- 

1. The module identifies all text fields of included bundles and add them to
   the filter query via hook_views_query_alter().  
2. The custom views bulk action updates identified matching node fields via Entity API. 
