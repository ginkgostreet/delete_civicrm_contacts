<?php

/**
 * Tag CiviCRM Contact
 *
 * @param type $cont_id
 * @param type $tag_id
 * @return type
 */
function tagContact($cont_id, $tag_id) {
  return cvCli('entity_tag', 'create', array( 'contact_id' => $cont_id, 'tag_id' => $tag_id));
}

/**
 * Create a "Job" Tag based on a date and script verb.
 *
 * @param type $date_str
 * @param type $job_action
 * @return type
 */
function createJobTag($date_str, $job_action) {
  $apiResult = cvCli('tag', 'create', array('name' => "$date_str $job_action", 'description' => "Bulk Script $job_action Contacts $date_str" ));

  $values = get_object_vars($apiResult->values);
  foreach($values as $tag) {
    cli\out("Created tag: {$tag->name} \n");
  }
  return $apiResult;
}

