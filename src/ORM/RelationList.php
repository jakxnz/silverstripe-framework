<?php

namespace SilverStripe\ORM;

use Exception;

/**
 * A DataList that represents a relation.
 *
 * Adds the notion of a foreign ID that can be optionally set.
 */
abstract class RelationList extends DataList implements Relation
{

    /**
     * @var RelationListObserver
     */
    protected $observer;

    /**
     * When cloning this object, clone the observer objects as well
     */
    public function __clone()
    {
        if ($this->observer) {
            $this->observer = clone $this->observer;
        }

        return parent::__clone();
    }

    /**
     * @param $observer
     * @return static
     */
    public function setObserver($observer)
    {
        $list = clone $this;

        $list->observer = $observer;

        return $list;
    }

    /**
     * @return RelationListObserver
     */
    public function getObserver()
    {
        return $this->observer;
    }

    /**
     * Establish which IDs we expect to see changed
     *
     * @param array $ids
     * @param bool $forceReset Ignore existing changed data
     * @return static
     */
    public function prepareObserver($items, $forceReset = false)
    {
        if (!$this->getObserver()) {
            return $this;
        }

        if ($this->getObserver()->isPrescribed()) {
            if (!$forceReset) {
                throw new \Exception('Existing changed data must be reset before changes can be prescribed');
            }
        }

        $ids = [];

        foreach ($items as $item) {
            $ids[] = !is_numeric($item) ? $item->ID : $item;
        }

        return $this->setObserver($this->getObserver()->prescribeUpdate($ids));
    }

    /**
     * @param array $items
     * @return DataList
     */
    public function addMany($items)
    {
        $observer = $this->getObserver();

        if ($observer && !$observer->isPrescribed()) {
            return $this->prepareObserver($items)->addMany($items);
        }

        return parent::addMany($items);
    }

    /**
     * @return DataList
     */
    public function removeAll()
    {
        $observer = $this->getObserver();

        if ($this->count() && $observer && !$observer->isPrescribed()) {
            return $this->prepareObserver($this->column('ID'))->removeAll();
        }

        return parent::removeAll();
    }

    /**
     * @param array $idList
     */
    public function setByIDList($idList)
    {
        $observer = $this->getObserver();

        $diff = array_diff($this->column('ID'), $idList);

        if (count($diff) && $observer && !$observer->isPrescribed()) {
            return $this->prepareObserver($diff)->removeAll();
        }

        parent::setByIDList($idList);
    }

    /**
     * Any number of foreign keys to apply to this list
     *
     * @return string|array|null
     */
    public function getForeignID()
    {
        return $this->dataQuery->getQueryParam('Foreign.ID');
    }

    public function getQueryParams()
    {
        $params = parent::getQueryParams();

        // Remove `Foreign.` query parameters for created objects,
        // as this would interfere with relations on those objects.
        foreach (array_keys($params) as $key) {
            if (stripos($key, 'Foreign.') === 0) {
                unset($params[$key]);
            }
        }

        return $params;
    }

    /**
     * Returns a copy of this list with the ManyMany relationship linked to
     * the given foreign ID.
     *
     * @param int|array $id An ID or an array of IDs.
     *
     * @return static
     */
    public function forForeignID($id)
    {
        // Turn a 1-element array into a simple value
        if (is_array($id) && sizeof($id) == 1) {
            $id = reset($id);
        }

        // Calculate the new filter
        $filter = $this->foreignIDFilter($id);

        $list = $this->alterDataQuery(function (DataQuery $query) use ($id, $filter) {
            // Check if there is an existing filter, remove if there is
            $currentFilter = $query->getQueryParam('Foreign.Filter');
            if ($currentFilter) {
                try {
                    $query->removeFilterOn($currentFilter);
                } catch (Exception $e) {
                    /* NOP */
                }
            }

            // Add the new filter
            $query->setQueryParam('Foreign.ID', $id);
            $query->setQueryParam('Foreign.Filter', $filter);
            $query->where($filter);
        });

        return $list;
    }

    /**
     * Returns a where clause that filters the members of this relationship to
     * just the related items.
     *
     *
     * @param array|integer $id (optional) An ID or an array of IDs - if not provided, will use the current ids as
     * per getForeignID
     * @return array Condition In array(SQL => parameters format)
     */
    abstract protected function foreignIDFilter($id = null);
}
