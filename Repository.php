<?php

require_once "FoxmlDocument.php";
require_once "Object.php";

abstract class AbstractRepository extends MagicProperty implements ArrayAccess {
  public $available;
  //abstract public function newObject($pid);
  //abstract public function getObject($pid);
  //abstract public function findObjects(array $search);
}

class FedoraRepository extends AbstractRepository {
  public $available = FALSE;
  protected $cache;

  public function __construct(FedoraApi $api, AbstractCache $cache) {
    $this->api = $api;
    $this->cache = $cache;
  }

  public function findObjects(array $search) {
  }

  /**
   * @todo validate the ID
   * @todo catch the getNextPid errors
   */
  public function getNewObject($id = NULL) {
    if($this->cache->get($id) !== FALSE) {
      return FALSE;
    }

    $exploded = explode(':', $id);

    if(!$id) {
      $id = $this->api->m->getNextPid();
    }
    elseif (count($exploded) == 1) {
      $id = $this->api->m->getNextPid($exploded[0]);
    }

    return new NewFedoraObject($id, $this);
  }

  /*
   * @todo error handling
   */
  public function ingestNewObject(NewFedoraObject &$object) {
    $dom = new FoxmlDocument($object);
    $xml = $dom->saveXml();
    $id = $this->api->m->ingest(array('string' => $xml));
    $object = new FedoraObject($id, $this);
    $this->cache->set($id, $object);
    return $object;
  }

  public function getObject($id) {
    $object = $this->cache->get($id);
    if($object !== FALSE) {
      return $object;
    }

    try {
      $object = new FedoraObject($id, $this);
      $this->cache->set($id, $object);
      return $object;
    }
    catch (RepositoryException $e) {
      // check to see if its a 401 or a 404
      $previous = $e->getPrevious();
      if($previous && ($previous->getCode == 404 || $previous->getCode == 401)) {
        return NULL;
      }
      else {
        // @todo fix this, it should throw something else.
        throw $e;
      }
    }
  }

  public function purgeObject($id) {
    $object = $this->cache->get($id);
    if($object !== FALSE) {
      $this->cache->delete($id);
    }

    try {
      $this->api->m->purgeObject($id);
    }
    catch (RepositoryException $e) {
      // @todo chain exceptions here.
      throw $e;
    }
  }

  public function offsetExists ( $offset ) {}
  public function offsetGet ( $offset ) {}
  public function offsetSet ( $offset , $value ) {}
  public function offsetUnset ( $offset ) {}
}