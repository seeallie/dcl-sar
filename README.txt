Introduction
------------

SAR, search and replace, allows searching and replaceing a
string against all text fields of node bundles via views. 


Dependencies
------------

1. SAR requires Views Bulk Operations module
https://www.drupal.org/project/views_bulk_operations.
2. The installed view requires "Page" as a content type. 


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
4. Go to /content-bulk-edit/vbo-sar to use the functionality of the module. 


Design requirements 
-------------------

- Present a user/content admin-friendly interface to search for a string in the content in the system.
- Once the content containing the string is rendered, the content user can enter a replacement string to replace the target string.
- The content user can then select which content items to be include to apply the "search and replace" (sar) action. This includes a 'select all' option. 
- The content user can then choose to apply sar action to the selected items. 
- The system executes the action, and displays a status message once the job is done. 


Features
-------- 

1. The module differs from the flow of VBO and VBO edit modules. It does not contain a configuraiton step, while the custom search and replace text fields are part of the view that the module works with. 
2. Once content types selected/included in the view as a filter, the module identifies all text fields of included bundles and add them to
   the filter query via hook_views_query_alter().  
2. The custom views bulk action updates identified matching node fields via Entity API. 
