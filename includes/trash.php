<?php

require_once __DIR__.'tag.php';


function trash_run($args) {
  define('ID_COLUMN', getOption('id-col', $args));
  $input = getOption('input-file', $args);
  $jobDate = shell('date +"%F %R"');

  if ($args['undelete']) {
    define('UNDELETE', TRUE);
    $apiTag = createJobTag($jobDate, 'UNDelete CiviCRM Contacts');
  } else {
    define('UNDELETE', FALSE);
    $apiTag = createJobTag($jobDate, 'Delete CiviCRM Contacts');
  }
  define('TAG_ID', $apiTag->id);

  $main = 'processContacts';
  withFile($input, $main);
}


/**
* Call api contact.delete for contact ID
*
* @param int $cont_id
*/
function markContactDeleted($cont_id) {
$apiResult = cvCli('contact', 'delete', array('id' => $cont_id));
if ($apiResult->is_error) {
cli\out(var_export($apiResult, TRUE));
} else {
cli\line("Contact ID#{$cont_id} moved to Trash.");
}
}

function removeFromTrash($cont_id) {
$apiResult = cvCli('contact', 'create', array('id' =>$cont_id, 'is_deleted'=> FALSE));
if ($apiResult->is_error) {
var_dump($apiResult);
} else {
cli\line("Contact ID#{$cont_id} removed from Trash.");
}
}

/**
 * Process a contact row
 *
 * @param array assoc $cont csv input line
 * @return void
 */
function processContact($cont) {
  if (!$cont) return;
  tagContact($cont[ID_COLUMN], TAG_ID);
  if (UNDELETE) {
    removeFromTrash($cont[ID_COLUMN]);
  } else {
    markContactDeleted($cont[ID_COLUMN]);
  }
}

$fieldDefinition = array();
/**
 * Called in the loop, so must use global variable;
 *
 * @global array $fieldDefinition
 * @param type $line
 * @param type $index
 */
function processContacts($line, $index) {
  global $fieldDefinition;

  processContact(parseCsv($line, $index, $fieldDefinition));
}
