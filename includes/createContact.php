<?php

function createContact_run($args) {
  global $config;
  $input = getOption('input-file', $args);
  define('CONTACT_TYPE', getOption('contact-type', $args));
  define('CONTACT_SUB_TYPE', getOption('contact-sub-type', $args));

  define('PHONE_TYPE', (array_key_exists("phone_type", $config)) ? $config['phone_type'] : "Main");
  define('WORK_PHONE_TYPE', (array_key_exists("work_phone_type", $config)) ? $config['work_phone_type'] : "Work");
  define('OTHER_PHONE_TYPE', (array_key_exists("other_phone_type", $config)) ? $config['other_phone_type'] : "Other");

  define('EMAIL_TYPE', (array_key_exists("email_type", $config)) ? $config['email_type'] : "Main");
  define('WORK_EMAIL_TYPE', (array_key_exists("work_email_type", $config)) ? $config['work_email_type'] : "Work");
  define('OTHER_EMAIL_TYPE', (array_key_exists("other_email_type", $config)) ? $config['other_email_type'] : "Other");

  define('ADDRESS_TYPE', (array_key_exists("address_type", $config)) ? $config['address_type'] : "Main");

  $main = 'processContactsForImport';
  withFile($input, $main);
}

$fieldDefinition = array();

function processContactsForImport($line, $index) {
  global $fieldDefinition;
  processContactForImport(parseCsv($line, $index, $fieldDefinition));
}


function processContactForImport($cont) {
  if (!$cont) return;
  $params = $cont;
  $email = array();
  $phone = array();
  $website = array();

  if(array_key_exists("email", $cont) && $cont['email']) {
    $email[] = array("email" => $cont['email'], 'location_type_id' => EMAIL_TYPE);
  }
  if(array_key_exists("work_email", $cont) && $cont['work_email']) {
    $email[] = array("email" => $cont['work_email'], 'location_type_id' => WORK_EMAIL_TYPE);
  }
  if(array_key_exists("other_email", $cont) && $cont['other_email']) {
    $email[] = array("email" => $cont['other_email'], 'location_type_id' => OTHER_EMAIL_TYPE);
  }


  if(array_key_exists("phone", $cont) && $cont['phone']) {
    $phone[] = array("phone" => $cont['phone'], 'location_type_id' => PHONE_TYPE);
  }
  if(array_key_exists("work_phone", $cont) && $cont['work_phone']) {
    $phone[] = array("phone" => $cont['work_phone'], 'location_type_id' => WORK_PHONE_TYPE);
  }
  if(array_key_exists("other_phone", $cont) && $cont['other_phone']) {
    $phone[] = array("phone" => $cont['other_phone'], 'location_type_id' => OTHER_PHONE_TYPE);
  }


  if(array_key_exists("website", $cont) && $cont['website']) {
    $website[] = array("url" => $cont['website'], 'website_type_id' => "Main");
  }
  if(array_key_exists("work_website", $cont) && $cont['work_website']) {
    $website[] = array("url" => $cont['work_website'], 'website_type_id' => "Work");
  }


  if(!array_key_exists("contact_type", $cont) && CONTACT_TYPE) {
    $params['contact_type'] = CONTACT_TYPE;
  }
  if(!array_key_exists("contact_sub_type", $cont) && CONTACT_SUB_TYPE) {
    $params['contact_sub_type'] = CONTACT_SUB_TYPE;
  }

  if(!empty($email)) {
    if(count($email) == 1) {
      $email = $email[0];
    }
    $params["api.Email.create"] = $email;
  }
  if(!empty($phone)) {
    if(count($phone) == 1) {
      $phone = $phone[0];
    }
    $params["api.Phone.create"] = $phone;
  }
  if(!empty($website)) {
    if(count($website) == 1) {
      $website = $website[0];
    }
    $params["api.Website.create"] = $website;
  }

  //handle Addresses.
  $address = array();

  if(array_key_exists("street_address", $cont)) {
    $address['street_address'] = $cont['street_address'];
  }
  if(array_key_exists("address_1", $cont)) {
    $address['supplemental_address_1'] = $cont['address_1'];
  }
  if(array_key_exists("city", $cont)) {
    $address['city'] = $cont['city'];
  }
  if(array_key_exists("state", $cont)) {
    $address['state'] = $cont['state'];
  }
  if(array_key_exists("postal_code", $cont)) {
    $address['postal_code'] = $cont['postal_code'];
  }
  if(array_key_exists("country", $cont)) {
    $address['country'] = $cont['country'];
  }
  if(array_key_exists("county", $cont)) {
    $address['country'] = $cont['county'];
  }
  if(array_key_exists("address_type", $cont)) {
    $address['location_type_id'] = $cont['address_type'];
  }

  if(!empty($address)) {
    if(!array_key_exists('location_type_id', $address)) {
      $address['location_type_id'] = ADDRESS_TYPE;
    }
    $params['api.Address.create'] = $address;
  }

  //remove extraneous keys
  $toRemove = array("email", "work_email", "other_email", "website", "phone", "other_phone", "work_phone", "street_address", "address_1", "country", "county", "state", "city", "postal_code");
  $params = array_diff_key($params, array_flip($toRemove));

  //Do the deed
  $result = cvCli("Contact", "create", $params);
  if($result['is_error'] != 0) {
    echo "Error creating contact,". implode(",", $cont);
  }
}


/*
 CRM.api3('Contact', 'create', {
  "sequential": 1,
  "contact_type": "Organization",
  "organization_name": "Whatever Test",
  "api.Phone.create": {"phone":"541-765-4321"},
  "api.Email.create": [{"email":"something@sometingelse.com"},{"email":"nothing@nobeeswax.org"}],
  "api.Website.create": {}
 */