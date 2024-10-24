<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class AddInstanceForm extends FormBase {

  protected $elementType;

  protected $elementName;

  public function getElementType() {
    return $this->elementType;
  }

  public function setElementType($elementType) {
    return $this->elementType = $elementType;
  }

  public function getElementName() {
    return $this->elementName;
  }

  public function setElementName($elementName) {
    return $this->elementName = $elementName;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_instance_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype = NULL) {

    //dpm($elementtype);

    if ($elementtype == NULL || $elementtype == "") {
      \Drupal::messenger()->addError(t("No element type has been provided"));
      self::backUrl();
      return;
    }

    $this->setElementName(NULL);
    $autocomplete = '';
    if ($elementtype == 'platforminstance') {
      $this->setElementName("Platform Instance");
      $autocomplete = 'dpl.platform_autocomplete';
    }
    if ($elementtype == 'instrumentinstance') {
      $this->setElementName("Instrument Instance");
      $autocomplete = 'dpl.instrument_autocomplete';
    }
    if ($elementtype == 'detectorinstance') {
      $this->setElementName("Detector Instance");
      $autocomplete = 'dpl.detector_autocomplete';
    }


    if ($this->getElementName() == NULL) {
      \Drupal::messenger()->addError(t("No VALID element type has been provided"));
      self::backUrl();
      return;
    }

    $this->setElementType($elementtype);

    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3>Create ' . $this->getElementName() . '</h3>'),
    ];
    $form['instance_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#autocomplete_route_name' => $autocomplete,
  ];
    $form['instance_serial_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Serial Number'),
    ];
    $form['instance_acquisition_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Acquisition Date'),
    ];
    $form['instance_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('instance_type')) < 1) {
        $form_state->setErrorByName('instance_type', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instance_serial_number')) < 1) {
        $form_state->setErrorByName('instance_number', $this->t('Please enter a valid name'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    $hascoType = '';
    if ($this->getElementType() == 'platforminstance') {
      $hascoType = VSTOI::PLATFORM_INSTANCE;
    }
    if ($this->getElementType() == 'instrumentinstance') {
      $hascoType = VSTOI::INSTRUMENT_INSTANCE;
    }
    if ($this->getElementType() == 'detectorinstance') {
      $hascoType = VSTOI::DETECTOR_INSTANCE;
    }

    $typeUri = '';
    if ($form_state->getValue('instance_type') != NULL && $form_state->getValue('instance_type') != '') {
      $typeUri = Utils::uriFromAutocomplete($form_state->getValue('instance_type'));
    }

    $acquisitionDate = '';
    if ($form_state->getValue('instance_acquisition_date') != NULL && $form_state->getValue('instance_acquisition_date') != '') {
      $acquisitionDate = $form_state->getValue('instance_acquisition_date');
    }

    $label = Utils::labelFromAutocomplete($form_state->getValue('instance_type')) . " with ID# " . $form_state->getValue('instance_serial_number');

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $newInstanceUri = Utils::uriGen($this->getElementType());
      $streamJson = '{"uri":"'.$newInstanceUri.'",' .
        '"typeUri":"'.$typeUri.'",'.
        '"hascoTypeUri":"'.$hascoType.'",'.
        '"label":"'.$label.'",'.
        '"hasSerialNumber":"'.$form_state->getValue('instance_serial_number').'",'.
        '"hasAcquisitionDate":"'.$acquisitionDate.'",'.
        '"comment":"'.$form_state->getValue('instance_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->elementAdd($this->getElementType(),$streamJson);
      \Drupal::messenger()->addMessage(t($this->getElementName() . " has been added successfully."));
      self::backUrl();
      return;
    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding stream: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.add_instance');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
