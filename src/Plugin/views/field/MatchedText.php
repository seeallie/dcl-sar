<?php

namespace Drupal\dcl_sar\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

use Drupal\dcl_sar\SearchAndReplaceTrait;

/**
 * Field handler to a computed field displaying matched text of a string.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("dcl_sar_view_matched_text")
 *
 * @see https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes
 */
class MatchedText extends FieldPluginBase {

  use SearchAndReplaceTrait;

  protected $searchString = '';
  protected $textFieldsArray = [];

  /**
   * @{inheritdoc}
   */
  public function query() {
    // This function exists to override parent query function so the SQL query
    // won't include the computed field 'matched_text' that doesn't exist
    // in the database.
  }

  /**
   * Define the available options.
   *
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Potential options: search in all text fields or search in selected text
    // fiels: providing a list of text field by node type.
    // See example: Drupal\node\Plugin\views\field\Path.php.
  }

  /**
   * Submit handler for the custom views form. Store search and replacement strings in session.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    // Set replacement_string and search_string in session.
    $replacement_string = $form_state->getValues()['replacement_string'];
    // Use one of the two ways to get the search_string value
    // Use the value in exposed filter: combine.
    $search_string = $this->getSearchString();
    // Use the value of the form field for the view @todo
    // $search_string = $form_state->getValues()['search_string'];
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('dcl_sar_collection');
    // Set the key/value pair in the database.
    $store->set('replacement_string', $replacement_string);
    $store->set('search_string', $search_string);
  }

  /**
   * Retrieve the search string from the current view.
   * The search string is set up using an exposed view filter.
   *
   * @todo make the view filter identifier as part of the module install config.
   */
  protected function getSearchString() {
    if (empty($this->searchString)) {
      $view = views_get_current_view();
      // Filter identifier set in the view.
      $input = $view->getExposedInput();
      if (!empty($input)) {
        $search = $view->getExposedInput()['sar_search'];
      }
      else {
        $search = '';
      }
      $this->searchString = $search;
    }
    return $this->searchString;
  }

  /**
   * Compute the field value: a list of strings each contains the search string
   * and its surrounding text.
   *
   * @see https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes
   */
  public function render(ResultRow $values) {
    $searchString = $this->getSearchString();

    foreach ($values as $key => $value) {
      if ($key === '_entity') {
        $entity = $value;
        $bundle = $value->getType();
      }
    }

    $matchedTextArray = $this->getMatchedTextFields($entity, $searchString);

    // Process matched data for rendering.
    if ($matchedTextArray) {
      $matches = '';
      foreach ($matchedTextArray as $field => $value) {
        $summary = $value[0];
        unset($value[0]);
        $valueStyled = [];
        foreach ($value as $val) {   
          $linkPattern = "/<a href=\"([^\"]*)\">(.*)<\/a>/U"; 

          if ( preg_match($linkPattern, $val) == 0) { 
            $val = str_replace($searchString, '<strong>' . $searchString . '</strong>', $val);
          }
          
          $valueStyled[] = $val;
        }

        $vals = array_values($valueStyled);

        $matches .= '<i>Found in field "' . $field . '"</i> : ' . $summary . ' <br><br>' . implode("...", $vals) . ' <br><br>';
      }
      
      return check_markup($matches, 'basic_html');
    }
    else {
      return NULL;
    }
  }

}
