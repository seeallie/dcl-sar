<?php

namespace Drupal\dcl_sar\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\views\Plugin\views\filter\StringFilter;

use Drupal\dcl_sar\SearchAndReplaceTrait;

/**
 * DEV. Filter handler which allows to search on multiple text fields.
 * 
 * @see Drupal\views\Plugin\views\filter\Combine 
 *
 * @ingroup views_field_handlers
 *
 * @ViewsFilter("dcl_sar_combine_all_text")
 */
class StringAllTextFields extends StringFilter {

  use SearchAndReplaceTrait;

  /**
   * All words separated by spaces or sentences encapsulated by double quotes.
   */
  const WORDS_PATTERN_SENSITIVE = '/ (-?)("[^"]+"|[^" ]+)/';  

  /**
   * @var views_plugin_query_default
   */
  public $query;

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['fields'] = ['default' => []];

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $this->view->initStyle();

    // Allow to choose all fields as possible
    if ($this->view->style_plugin->usesFields()) {
      $options = [];
      foreach ($this->view->display_handler->getHandlers('field') as $name => $field) {
        // Only allow clickSortable fields. Fields without clickSorting will
        // probably break in the Combine filter.
        if ($field->clickSortable()) {
          $options[$name] = $field->adminLabel(TRUE);
        }
      }
      if ($options) {
        $form['fields'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose fields to combine for filtering'),
          '#description' => $this->t("This filter doesn't work for very special field handlers."),
          '#multiple' => TRUE,
          '#options' => $options,
          '#default_value' => $this->options['fields'],
        ];
      }
      else {
        $form_state->setErrorByName('', $this->t('You have to add some fields to be able to use this filter.'));
      }
    }
  }

    public function operators() {
    $operators = [
      'contains' => [
        'title' => $this->t('Contains'),
        'short' => $this->t('contains'),
        'method' => 'opContains',
        'values' => 1,
      ],
      'word' => [
        'title' => $this->t('Contains any word'),
        'short' => $this->t('has word'),
        'method' => 'opContainsWord',
        'values' => 1,
      ],
      'allwords' => [
        'title' => $this->t('Contains all words'),
        'short' => $this->t('has all'),
        'method' => 'opContainsWord',
        'values' => 1,
      ],
      'regular_expression' => [
        'title' => $this->t('Regular expression'),
        'short' => $this->t('regex'),
        'method' => 'opRegex',
        'values' => 1,
      ],
    ];

    return $operators;
  }

  public function query() {
    // Do nothing: do not add text fields here due to lack of how-to info. 
    // Instead add in views_execution.inc via hook_views_query_alter(). 
  }
    
  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();
    if ($this->displayHandler->usesFields()) {
      $fields = $this->displayHandler->getHandlers('field');
      foreach ($this->options['fields'] as $id) {
        if (!isset($fields[$id])) {
          // Combined field filter only works with fields that are in the field
          // settings.
          $errors[] = $this->t('Field %field set in %filter is not set in display %display.', ['%field' => $id, '%filter' => $this->adminLabel(), '%display' => $this->displayHandler->display['display_title']]);
          break;
        }
        elseif (!$fields[$id]->clickSortable()) {
          // Combined field filter only works with simple fields. If the field
          // is not click sortable we can assume it is not a simple field.
          // @todo change this check to isComputed. See
          // https://www.drupal.org/node/2349465
          $errors[] = $this->t('Field %field set in %filter is not usable for this filter type. Combined field filter only works for simple fields.', ['%field' => $fields[$id]->adminLabel(), '%filter' => $this->adminLabel()]);
        }
      }
    }
    else {
      $errors[] = $this->t('%display: %filter can only be used on displays that use fields. Set the style or row format for that display to one using fields to use the combine field filter.', ['%display' => $this->displayHandler->display['display_title'], '%filter' => $this->adminLabel()]);
    }
    return $errors;
  }

  protected function opContains($expression) {
    $placeholder = $this->placeholder();
    $this->query->addWhereExpression($this->options['group'], "$expression LIKE $placeholder", [$placeholder => '%' . db_like($this->value) . '%']);
  }

  /**
   * Filters by one or more words.
   *
   * By default opContainsWord uses add_where, that doesn't support complex
   * expressions.
   *
   * @param string $expression
   */
  protected function opContainsWord($expression) {
    $placeholder = $this->placeholder();

    // Don't filter on empty strings.
    if (empty($this->value)) {
      return;
    }

    // Match all words separated by spaces or sentences encapsulated by double
    // quotes.
    preg_match_all(static::WORDS_PATTERN_SENSITIVE, ' ' . $this->value, $matches, PREG_SET_ORDER);

    // Switch between the 'word' and 'allwords' operator.
    $type = $this->operator == 'word' ? 'OR' : 'AND';
    $group = $this->query->setWhereGroup($type);
    $operator = Database::getConnection()->mapConditionOperator('LIKE');
    $operator = isset($operator['operator']) ? $operator['operator'] : 'LIKE';

    foreach ($matches as $match_key => $match) {
      $temp_placeholder = $placeholder . '_' . $match_key;
      // Clean up the user input and remove the sentence delimiters.
      $word = trim($match[2], ',?!();:-"');
      $this->query->addWhereExpression($group, "$expression $operator $temp_placeholder", [$temp_placeholder => '%' . Database::getConnection()->escapeLike($word) . '%']);
    }
  }

  protected function opRegex($expression) {
    $placeholder = $this->placeholder();
    $this->query->addWhereExpression($this->options['group'], "$expression REGEXP $placeholder", [$placeholder => $this->value]);
  }

}
