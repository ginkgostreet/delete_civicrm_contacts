<?php


function bootstrap_civicrm() {
  require_once(CIVICRM_ROOT.'/bin/cli.class.php');
  $cli = new civicrm_Cli();
  $_SERVER['argv'][] = '-e=contact';
  $cli->initialize() || die('Died during initialization');
  require_once(CIVICRM_ROOT.'/api/api.php');
}

function validateApiConfig() {
  $apiResult = cvCli('domain', 'get');
  if (!$apiResult || $apiResult->is_error == 1) {
    echo "FAILED to validate API connection\n";
    if (@$apiResult->is_error) {
      echo $apiResult->error_message;
    }
    die("\nexit");
  }
}

/**
 * diagnostic function
 *
 * @param type $line
 * @param type $index
 */
function echoLine($line, $index) {
  echo $index . ':' . $line . "\n";
}

/**
 * This function takes a list of column headers and
 * Uses mappings contained in the config to map their
 * names to whatever names will be required for api
 * calls
 *
 * @param array $columns
 */
function mapColumns($columns) {
  global $config;
  if (array_key_exists('MAPPINGS', $config)) {
    foreach($columns as &$column) {
      if(array_key_exists($column, $config['MAPPINGS'])) {
        $column = $config['MAPPINGS'][$column];
      }
    }
  }
  return $columns;
}


/**
 * Utility to get user option or it's default.
 *
 * @param string $opt option name
 * @param array $args cli\Arguments object
 * @return mixed
 */
function getOption($opt, $args) {
  $user = $args->getArguments();
  if ($args[$opt]) {
    $val = $user[$opt];
  } else {
    $arg = $args->getOption($opt);
    $val = ( array_key_exists('default', $arg) ) ? $arg['default'] : null;
  }
  return $val;
}




/**
 * Loop Callback utility to create an array from a csv file.
 * Associative array if $index and $fieldDefinition are provided.
 *
 * @param string $line
 * @param int $index
 * @param array $fieldDefinition keys
 * @return array
 */
function parseCsv($line, $index=NULL, &$fieldDefinition=NULL) {
  $values = str_getcsv($line, ',', '"');
  if ($index === 0) {
    $fieldDefinition = mapColumns($values);
  } else if ($fieldDefinition) {
    if (count($fieldDefinition) == count($values)) {
      return array_combine($fieldDefinition, $values);
    } else {
      cli\err("field definition did not match:\n". var_export($values, TRUE));
    }
  } else {
    return $values;
  }
}

/**
 * Use civicrm/bin/cli.php to call the API
 * NOTE: requires core hack to add --json output option.
 * https://gist.github.com/ginkgomzd/b26a750b2fbd3ce25950
 *
 * @param type $entity
 * @param type $action
 * @param type $params
 * @return type
 */
function cvCli($entity, $action, $params = array()) {
  if (function_exists("civicrm_api3")) {
    try {
      return civicrm_api3($entity, $action, $params);
    } catch (CiviCRM_API3_Exception $e) {
      echo $e->getMessage();
      return null;
    }
  }

  $cmdApi = CIVICRM_ROOT.'/bin/cli.php';
  $clParams = '';
  foreach ($params as $key => $value) {
    $clParams .= " --$key=\"$value\"";
  }
  $call = "php $cmdApi -e $entity -a $action $clParams --json";
  echo $call."\n";
  $apiResult = json_decode(shell($call));
  return $apiResult;
}

/**
 * Execute drush cvapi
 * confirm CiviCRM Root
 *
 * @param $apiCall e.g. ext.install key='org.civicrm.volunteer'
 * @param $toStdOut FALSE
 * @return PHP Object from CiviCRM API result
 **/
function drushCVApi($entity, $action, $params, $toStdOut=FALSE) {
  $CVAPI = 'drush cvapi';
  $apiCall = "$entity.$action $params";

  if (!is_dir(CIVICRM_ROOT)) {
    throw new Exception('CIVICRM_ROOT is undefined');
  }

  $cacheDir = getcwd();
  chdir(CIVICRM_ROOT);

  $output = $return_var = null;
  if ($toStdOut) {
    system("$CVAPI $apiCall --out=pretty", $return_var);
    chdir($cacheDir);
    return $return_var;
  } else {
    exec("$CVAPI $apiCall --out=json", $output, $return_var);
    chdir($cacheDir);
    return json_decode(join($output));
  }
}

/**
 * Wrap php exec function with some helpers.
 * Returns output of command.
 *
 * @param type $call system call
 * @param string $chDir execute command in this dir
 * @return string
 */
function shell($call, $chDir=NULL) {
  if ($chDir) {
    $cacheDir = getcwd();
    chdir(CIVICRM_ROOT);
  }
  exec($call, $output, $return_var);

  if ($chDir) {
    chdir($cacheDir);
  }

  return join($output);
}

/**
 * Try to get configs from a .conf file.
 *
 * e.g. -
 * {
 *   "config": {
 *   "CIVICRM_ROOT": "/var/www/ncba/ncba/sites/all/modules/civicrm"
 *   }
 * }
 *
 * usage: @extract(getConfig());
 *
 * Safety for extract... enforce use of "config" envelope;
 *
 * @return array associative
 */
function getConfig() {
  $confFile = __DIR__.'/../.conf';
  if (file_exists($confFile)) {
    $file = file_get_contents($confFile);
    $configs = json_decode($file, TRUE); //assoc array
  }
  return (array_key_exists('config', $configs) && count($configs) === 1)
    ? $configs
    : null
    ;
}

/**
 * Callback per input line.
 *
 * @param file $file string or resource
 * @param string $function_name e.g. - myCallback($line, $index) {...}
 * @param array $dontMask Optional. Remove items from character_mask argument to trim() - e.g. array("\n", "\t"); Note double-quotes.
 * @throws Exception
 **/
function withFile($file, $function_name, $dontMask = array()) {

  if ( !is_resource($file)) {
    //try to open it
    $file = fopen($file, 'r');
  } else if (get_resource_type($file) !== "stream") {
    throw new Exception("Resources is not a file.");
  }

  if ( !function_exists($function_name)) {
    throw new Exception("function name, \"$function_name\" does not exist;");
  }

  $dontMask = array_fill_keys($dontMask, '');
  $charmask = strtr(" \t\n\r\0\x0B", $dontMask);

  $n=0;
  while (($line = fgets($file)) !== FALSE) {
    $function_name(trim($line, $charmask), $n);
    $n++;
  }

  $meta = stream_get_meta_data($file);
  if ($meta['uri'] != 'php://stdin') {
    fclose($file);
  }
}
