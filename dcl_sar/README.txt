Introduction
------------

The SAR, search and replace, custom module allows searching and replaceing a
string against all text fields of node bundles via Drupal's content views 
interface. 


Requirements
------------

SAR requires Views Bulk operations module to be installed.
https://www.drupal.org/project/views_bulk_operations


Installation
------------

Install as any other Drupal 8 module.


Configuration
-------------

1. Create a new content view 'content bulk edit'. 
2. Add a "Views bulk operations" field (global), available on
   all entity types, if not already added.
3. Check the "Search and replace text" action.
4. Add an exposed filter 'Combine fields filter' (global),
   enter 'sar_search' as the filter identifier; select 'title' and 'body' from
   the 'Choose fields to combine for filtering' dropdown menu, and apply
   the filter. 


Features
-------- 

1. The module identifies all text fields of included bundles and add them to
   the filter query via hook_views_query_alter().  
2. The custom views bulk action updates identified matches via Entity API. 
3. The module does not include items of custom blocks or menu links. 


Future Development
------------------

1. Include paragraphs and Link field beyond node text fields.
2. Include media entity for caption, alt text.
3. Implement process management: define access (permission and user role)
   and failure recovery process.
4. Provide the option of making the update per field, in addition to per node.
5. Provide a config step for selecting individual text fields to include.  