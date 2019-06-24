<?php

namespace Drupal\dcl_sar;

use Drupal\node\Entity\Node; 
use Drupal\Component\Utility\Html;
use Drupal\views\ResultRow;

/**
 * Defines common methods for Search and Replacement Action.
 */
trait SearchAndReplaceTrait {

  /**
   * The array object that stores all text fields of a bundle.
   *
   * @var array
   */
  protected $textFieldsArray = [];

  /**
   * Get all text fields of node bundles in a view.
   */
  //public function getMatchedTextFields(ResultRow $values, string $search_string) {
  public function getMatchedTextFields(Node $entity, string $search_string) {
    if (empty($search_string)) {
      return NULL;
    }

    $matchedTextArray = [];
    $matchedText = [];

    // For each row, find node type, and then all text fields associated;
    // Loop through each text field to find matches with surouding text;
    // Generate data structures to hold such matches.
    $entity_type = 'node';
    $bundle = $entity->getType();

    // Load all fields of the bundle.
    $entityManager = \Drupal::service('entity_field.manager');
    $fields = $entityManager->getFieldDefinitions($entity_type, $bundle);

    // Get the text fields, title and body only.
    // @todo maybe need a recursive way to find all sub text fields
    if (!isset($this->textFieldsArray[$bundle])) {
      $textFields = $this->getTextFieldsOnly($bundle, $fields);
    }
    else {
      $textFields = $this->getTextFields($bundle);
    }

    // Get value from entities, not database.
    // @see execute() in ModifyEntityValues.php of views bulk edit module
    foreach ($textFields as $field => $value) {
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storageDefinition */
      $storageDefinition = $entity->{$field}->getFieldDefinition()->getFieldStorageDefinition();
      $cardinality = $storageDefinition->getCardinality();
      // @todo Check for valid cardinality value: either a positive integer or -1
      // if ($cardinality === $storageDefinition::CARDINALITY_UNLIMITED || $cardinality > 0) {
      if ($cardinality == 1) {
        // This always return an array.
        $value = $entity->{$field}->getValue();
        if ($value) {
          // Get the first value of the array since cardinality is 1.
          // @todo need to check all values when cardinality is bigger than 1
          $fieldValue = $value[0]['value'];
          $matchedText = $this->findMatches($field, $fieldValue, $search_string);
          if (!empty($matchedText)) {
            $matchedTextArray[$field] = $matchedText;
          }
        }
      }
    }

    return $matchedTextArray;
  }

  /**
   * Retrieve all text fields defined for a bundle.
   */
  protected function getTextFieldsOnly(string $bundle, array $fields) {
    // Get only text form elements, including title and body
    // Drupal default fields.
    $target_elements = ['title', 'body'];
    // User defined and created content fields.
    $target_field_prefix = 'field_';
    // Field type identifier, covering textarea, text_format, etc.
    $text_field_prefix = 'text';

    // https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element.php/function/Element%3A%3Achildren/8.7.x
    foreach ($fields as $key => $value) {
      if ($key === 'title'
        || $key === 'body') {
        $this->textFieldsArray[$bundle][$key] = 0;
      }
      elseif (strpos($value->getName(), $target_field_prefix) === 0) {
        if (strpos($value->getType(), $text_field_prefix) === 0) {
          $this->textFieldsArray[$bundle][$key] = 0;
        }
      }
    }
    return $this->textFieldsArray[$bundle];
  }

  /**
   * Helper function to return the value of text field array for a bundle.
   */
  protected function getTextFields(string $bundle) {
    if ($this->textFieldsArray[$bundle]) {
      return $this->textFieldsArray[$bundle];
    }
    return NULL;
  }

  /**
   * Helper function to find surrounding text of a matched search string.
   *
   * @todo: improve the logic for finding appropriate surrounding text
   */
  protected function findMatches($field, $field_value, $search_string) {
    // Form search pattern with words surrouding search string.
    $count = substr_count($field_value, $search_string);
    $found = [];
    $length = 255;

    if ($count) {
      $summary = $count . ($count > 1 ? ' matches.' : ' match.');
      $found[] = $summary;

      // If found matches in 'title' or fields with short values,
      // just return the entire text without parsing.
      if ($field === 'title' || strlen($field_value) <= $length) {
        $found[] = $field_value;
        return $found;
      }

      // If the field is long, find the matches and their surrounding text.
      // Treating the field as html content: maintain html tags integrity;
      // address special character situations; process html link tag and text

      // Remove newline and break 
      $fieldValueClean = preg_replace('/^\s+|\n|\r|\s+$/m', '', $field_value);
      // Use HTML DOM parser to proces the content instead of using regex:
      // HTML is too irregular for using regex
      $html = Html::load($fieldValueClean);
      $targetTags = array('li', 'p', 'h2','h3');
      $foundText = [];
      foreach ($targetTags as $tag) {
        $foundText = $this->getHTMLElements($html, $tag, $search_string);
        if ($foundText) {
          $found = array_merge($found, $foundText);  
        }
      }
    }
    return $found;
  }

  /**
   * Helper function to get HTML dom element that contains the search string.
   *
   * @todo: add logic for dom element longer than 255. 
   */
  protected function getHTMLElements(\DOMDocument $dom, $tag_name, $search_string) {
    $elements = $dom->getElementsByTagName($tag_name);
    $foundText = [];
    $length = 255;
    
    // Check for links as child nodes of the tag first. 
    $length = $elements->length;
    for ($i = $length; --$i >= 0;) {
      $children = $elements->item($i);
      $linkElements = $children->getElementsByTagName('a');
      if ($linkElements) {
        foreach ($linkElements as $linkEle) {
          $linkValue = $linkEle->attributes['href']->nodeValue;
          if (strpos($linkEle->textContent, $search_string) !== false || strpos($linkValue, $search_string) !== false) {
              $foundText[] = '<a href="' . $linkValue. '">' . $linkEle->textContent . '</a>';
            // Remove the child link from its parent to avoid being matched again.
            $linkEle->parentNode->removeChild($linkEle);    
          }      
        }
      }
    }  

    // Check the tag node itself
    foreach ($elements as $ele) {
      if (strpos($ele->textContent, $search_string) !== false) {
        if (strlen($ele->textContent) <= $length) {
          $foundText[] = '<' . $ele->tagName . '>' . $ele->textContent . '</' . $ele->tagName . '>';
        } 
        else {
          // return the entire element for now
          // @todo: more appropriate processing to extract the right length around the target string.
          $foundText[] = '<' . $ele->tagName . '>' . $ele->textContent . '</' . $ele->tagName . '>';
        }
      }
    }
    return $foundText;
  }
}
