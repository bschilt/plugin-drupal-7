<?php

namespace Drutiny\Plugin\Drupal7\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 * Check a configuration is set correctly.
 */
class VariableCompare extends Audit {

  /**
   * @inheritDoc
   */
  public function audit(Sandbox $sandbox) {
    $key = $sandbox->getParameter('key');
    $value = $sandbox->getParameter('value');

    if ($required_modules = $sandbox->getParameter('required_modules')) {
      $required_modules = is_array($required_modules) ? $required_modules : [$required_modules];
      $info = $sandbox->drush(['format' => 'json'])->pmList();
      $missing_modules = array_diff($required_modules, array_keys($required_modules));

      if (!empty($missing_modules)) {
        return Audit::NOT_APPLICABLE;
      }
    }

    try {
      $vars = $sandbox->drush([
        'format' => 'json'
        ])->variableGet();

      if (!isset($vars[$key])) {
        throw new DrushFormatException(__CLASS__ . ": $key is not a set variable.", '');
      }
      $reading = $vars[$key];
    }
    catch (DrushFormatException $e) {
      $sandbox->setParameter('exception', $e->getMessage());

      $default_value = $sandbox->getParameter('default', 'no-value-provided');

      // If no default value was provided then we can not provide an accruate
      // outcome based on the absense of a successful drush command.
      if ($default_value === 'no-value-provided') {
        return FALSE;
      }

      $reading = $default_value;
    }

    $sandbox->setParameter('reading', $reading);

    $comp_type = $sandbox->getParameter('comp_type', '==');
    $sandbox->logger()->info('Comparative config values: ' . var_export([
      'reading' => $reading,
      'value' => $value,
      'expression' => 'reading ' . $comp_type . ' value',
    ], TRUE));

    switch ($comp_type) {
      case 'lt':
      case '<':
        return $reading < $value;
      case 'gt':
      case '>':
        return $reading > $value;
      case 'lte':
      case '<=':
        return $reading <= $value;
      case 'gte':
      case '>=':
        return $reading >= $value;
      case 'ne':
      case '!=':
        return $reading != $value;
      case 'nie':
      case '!==':
        return $reading !== $value;
      case 'matches':
      case '~':
        return strpos($reading, $value) !== FALSE;
      case 'identical':
      case '===':
        return $value === $reading;
      case 'in_array':
        return in_array($reading, $value);
      case 'equal':
      case '==':
      default:
        return $value == $reading;
    }
  }

}
