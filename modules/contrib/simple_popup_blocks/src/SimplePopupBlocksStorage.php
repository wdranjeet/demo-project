<?php

namespace Drupal\simple_popup_blocks;

/**
 * Class SimplePopupBlocksStorage.
 */
class SimplePopupBlocksStorage {

  /**
   * Save an entry in the simple_popup_blocks table.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int
   *   The number of updated rows.
   *
   * @throws \Exception
   *   When the database insert fails.
   *
   * @see db_insert()
   */
  public static function insert(array $entry) {
    $return_value = NULL;
    try {
      $return_value = db_insert('simple_popup_blocks')
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      drupal_set_message(t('db_insert failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]
      ), 'error');
    }
    return $return_value;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   *
   * @see db_update()
   */
  public static function update(array $entry) {
    try {
      // db_update()...->execute() returns the number of rows updated.
      $count = db_update('simple_popup_blocks')
        ->fields($entry)
        ->condition('pid', $entry['pid'])
        ->execute();
    }
    catch (\Exception $e) {
      drupal_set_message(t('db_update failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]
      ), 'error');
    }
    return $count;
  }

  /**
   * Load single popup from table with pid.
   */
  public static function load($pid) {
    $select = db_select('simple_popup_blocks', 'pb');
    $select->fields('pb');
    $select->condition('pid', $pid);

    // Return the result in object format.
    return $select->execute()->fetchAll();
  }

  /**
   * Load single popup from table with identifier.
   */
  public static function loadCountByIdentifier($identifier) {
    try {
      $select = db_select('simple_popup_blocks', 'pb');
      $select->fields('pb', ['pid']);
      $select->condition('identifier', $identifier);
      // Return the result in object format.
      // countQuery()->execute()->fetchField();//
      return $select->execute()->fetchAll();
    }
    catch (\Exception $e) {
      drupal_set_message(t('db_select loadCountByIdentifier failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]
      ), 'error');
    }
  }

  /**
   * Load all popup from table.
   */
  public static function loadAll() {
    $select = db_select('simple_popup_blocks', 'pb');
    $select->fields('pb');

    // Return the result in object format.
    return $select->execute()->fetchAll();
  }

  /**
   * Delete popup from table.
   */
  public static function delete($pid) {
    $select = db_delete('simple_popup_blocks');
    $select->condition('pid', $pid);

    // Return the result in object format.
    return $select->execute();
  }

}
