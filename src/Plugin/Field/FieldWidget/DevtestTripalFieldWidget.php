<?php declare(strict_types = 1);

namespace Drupal\tripal_devtools\Plugin\Field\FieldWidget;

use Drupal\tripal\TripalField\TripalWidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'devtest_tripal_field_widget' field widget for 'devtest_tripal_field'.
 *
 * @FieldWidget(
 *   id = "devtest_tripal_field_widget",
 *   label = @Translation("DevTest Tripal Field Widget"),
 *   description = @Translation("AUTO GENERATED by Tripal DevTools for Testing."),
 *   field_types = {
 *     "devtest_tripal_field"
 *   }
 * )
 */
class DevtestTripalFieldWidget extends TripalWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Define your form elements here.
    // If you have chosen to make a widget which does not allow editing of the
    // data then you should display it in disabled elements.
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->value ?? '',
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ];

    return $element;
  }

}
