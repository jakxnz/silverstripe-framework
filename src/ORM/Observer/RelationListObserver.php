<?php


namespace SilverStripe\ORM\Observer;

/**
 * Interface RelationListObserver
 *
 * An observer for relation lists, to oversee things like updates/mutations to the list
 *
 * @package SilverStripe\ORM
 */
interface RelationListObserver
{

    public const CHANGED_TYPE_ADDED = 'added';
    public const CHANGED_TYPE_REMOVED = 'removed';

    /**
     * Set the classname for the list's items
     *
     * @param string $className
     * @return RelationListObserver
     */
    public function setDataClass($className);

    /**
     * Set the classname for the foreign relation
     *
     * @param string $className
     * @return RelationListObserver
     */
    public function setForeignRelationClass($className);


    /**
     * Set the relation name for the owner
     *
     * @param string $name
     * @return RelationListObserver
     */
    public function setForeignRelationName($name);

    /**
     * Telegraph the expected impact of an upcoming update to the relation list
     *
     * @param array $ids
     * @return RelationListObserver
     */
    public function prescribeUpdate($ids = []);

    /**
     * Handle an update to the relation list
     *
     * @param DataObject|int $item
     * @param array $extraFields
     * @param null $type
     */
    public function updateItem($item, $extraFields = [], $type = null);

    /**
     * Returns true when this observer has an upcoming update prescribed
     *
     * @return bool
     */
    public function isPrescribed();

}
