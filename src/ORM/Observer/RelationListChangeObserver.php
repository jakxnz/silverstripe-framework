<?php

namespace SilverStripe\ORM\Observer;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class RelationListObserver
 *
 * An "immutable" observer which tracks the changes to a relation list
 * and trigger onRelationChanged events.
 *
 * Attach this observer via the injector, e.g:
 *
 * SilverStripe\Core\Injector\Injector:
 *   SilverStripe\ORM\Observer\RelationListObserver:
 *     class: Silverstripe\ORM\Observer\RelationListChangeObserver
 *
 * @package SilverStripe\ORM
 */
class RelationListChangeObserver implements RelationListObserver
{
    use Injectable;
    use Extensible;

    /**
     * @var string
     */
    protected $dataClass;

    /**
     * @var string
     */
    protected $foreignRelationClass;

    /**
     * @var string
     */
    protected $foreignRelationName;

    /**
     * A temporary set of changes to items in this relation, used for
     * staging triggers and supplying changed data
     *
     * e.g [
     *   1 => [
     *     'className => 'SilverStripe\ORM\DataObject',
     *     'id' => 1,
     *     'extraFields' => [
     *        'fieldName' => 'value',
     *     ],
     *     'type' => 'added',
     *   ]
     * ]
     *
     * Type "added" should also be assigned for re-added items
     *
     * @var array $changed
     */
    protected $changed = [];

    /**
     * Set the classname for the list's items
     *
     * @param string $className
     * @return RelationListObserver
     */
    public function setDataClass($className)
    {
        $observer = clone $this;

        $observer->dataClass = $className;

        return $observer;
    }

    /**
     * @return string
     */
    public function getDataClass()
    {
        return $this->dataClass;
    }

    /**
     * Set the classname for the foreign relation
     *
     * @param string $className
     * @return RelationListObserver
     */
    public function setForeignRelationClass($class)
    {
        $observer = clone $this;

        $observer->foreignRelationClass = $class;

        return $observer;
    }

    /**
     * @return string
     */
    public function getForeignRelationClass()
    {
        return $this->foreignRelationClass;
    }

    /**
     * Set the relation name for the owner
     *
     * @param string $name
     * @return RelationListObserver
     */
    public function setForeignRelationName($name)
    {
        $observer = clone $this;

        $observer->foreignRelationName = $name;

        return $observer;
    }

    /**
     * @return string
     */
    public function getForeignRelationName()
    {
        return $this->foreignRelationName;
    }

    /**
     * Returns a list of changes recorded by the current mutation
     *
     * @return array The stored changed data
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * Establish which IDs we expect to see changed
     *
     * @param array $ids
     * @param bool $forceReset Ignore existing changed data
     * @return RelationListObserver A new instance of the observer
     */
    public function prescribeUpdate($ids = [])
    {
        $observer = clone $this;

        $observer->changed = array_fill_keys($ids, []);

        return $observer;
    }

    /**
     * Record details of a change to the relation
     *
     * @param int $id
     * @param array $extraFields
     * @param string $type e.g RelationListObserver::CHANGED_TYPE_ADDED
     */
    public function updateItem($item, $extraFields = [], $type = RelationListObserver::CHANGED_TYPE_ADDED)
    {
        $this->invokeWithExtensions('onBeforeUpdate', $item, $extraFields, $type);

        $itemID = is_object($item) ? $item->ID : (int) $item; // Assumes ID property is accessible, expects failure if not

        $className = $this->getDataClass();
        if (is_object($item)) {
            $className = get_class($item);
        }

        $this->changed[$itemID] = [
            'className' => $className,
            'id' => $itemID,
            'extraFields' => $extraFields,
            'type' => $type,
        ];

        if ($this->allChanged()) {
            // Trigger changed event
            $singleton = singleton($this->getForeignRelationClass());
            $name = $this->getForeignRelationName();
            $changed = $this->getChanged();
            // Invoke non-specific changed event
            $singleton->invokeWithExtensions('onRelationChanged', $name, $changed);
            // Invoke relation-specific changed event
            $singleton->invokeWithExtensions(sprintf('on%sChanged', $name), $changed);
        }
    }

    /**
     * Returns true if all prescribed changes have been fulfilled
     *
     * @return bool
     */
    public function allChanged()
    {
        $changed = $this->getChanged();

        $allChanged = count(array_column($changed, 'type')) == count($changed);

        $this->invokeWithExtensions('updateAllChanged', $allChanged);

        return $allChanged;
    }

    /**
     * Reset the observer's changed record
     *
     * @return RelationListObserver
     */
    public function reset()
    {
        $observer = clone $observer;

        $observer->changed = [];

        return $observer;
    }

    /**
     * @return bool
     */
    public function isPrescribed()
    {
        return (bool) count($this->getChanged());
    }
}
