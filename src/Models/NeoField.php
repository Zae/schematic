<?php

namespace NerdsAndCompany\Schematic\Models;

use Craft\Craft;
use Craft\BaseModel;
use Craft\FieldModel;
use Craft\FieldGroupModel;
use Craft\MatrixBlockTypeModel;

/**
 * Schematic Matrix Field Model.
 *
 * A schematic field model for mapping matrix data
 *
 * @author    Nerds & Company
 * @copyright Copyright (c) 2015-2017, Nerds & Company
 * @license   MIT
 *
 * @link      http://www.nerds.company
 */
class NeoField extends Field
{
    /**
     * Returns neo service.
     *
     * @return \Craft\NeoService
     */
    private function getNeoService()
    {
        return Craft::app()->neo;
    }

    /**
     * Returns schematic fields service.
     *
     * @return \NerdsAndCompany\Schematic\Services\Fields;
     */
    private function getFieldsService()
    {
        return Craft::app()->schematic_fields;
    }

    //==============================================================================================================
    //================================================  EXPORT  ====================================================
    //==============================================================================================================

    /**
     * @param FieldModel $field
     * @param $includeContext
     *
     * @return array
     */
    public function getDefinition(FieldModel $field, $includeContext)
    {
        $definition = parent::getDefinition($field, $includeContext);
        $definition['blockTypes'] = $this->getBlockTypeDefinitions($field);
        $definition['groups'] = $this->getGroupDefinition($field);

        return $definition;
    }

    /**
     * Get block type definitions.
     *
     * @param FieldModel $field
     *
     * @return array
     */
    protected function getBlockTypeDefinitions(FieldModel $field)
    {
        $blockTypeDefinitions = [];

        $groups = $this->getNeoService()->getGroupsByFieldId($field->id);
        $blockTypes = $this->getNeoService()->getBlockTypesByFieldId($field->id);
        $blockTypeFieldDefinitions = [];

        foreach ($blockTypes as $blockType) {
            $blockTypeDefinitions[$blockType->handle] = [
              'name' => $blockType->name,
              'childBlocks' => $blockType->childBlocks,
              'topLevel' => $blockType->topLevel,
              'sortOrder' => $blockType->sortOrder,
              'group' => $this->getNeoService()->getGroupsByFieldId($field->id)[0]->name,
              'fieldLayout' => $this->getFieldsService()->getFieldLayoutDefinition($blockType->getFieldLayout())
              /*'id' => AttributeType::Number,
              'dateCreated' => AttributeType::DateTime,
              'dateUpdated' => AttributeType::DateTime,
              'fieldId' => AttributeType::Number,
              'fieldLayoutId' => AttributeType::String,
              'handle' => AttributeType::String,
              'maxBlocks' => AttributeType::Number,
              'maxChildBlocks' => AttributeType::Number,*/
            ];
        }

        return $blockTypeDefinitions;
    }

    protected function getGroupDefinition(FieldModel $field)
    {
        /*'id' => AttributeType::Number,
			'fieldId' => AttributeType::Number,
			'name' => AttributeType::String,
			'sortOrder' => AttributeType::Number, */
        $groupDefinitions = [];

        $groups = $this->getNeoService()->getGroupsByFieldId($field->id);

        foreach ($groups as $group) {
            $groupDefinitions[$group->name] = [
                'name' => $group->name,
                'sortOrder' => $group->sortOrder
            ];
        }

        return $groupDefinitions;
    }

    //==============================================================================================================
    //================================================  IMPORT  ====================================================
    //==============================================================================================================

    /**
     * @param array                $fieldDefinition
     * @param FieldModel           $field
     * @param string               $fieldHandle
     * @param FieldGroupModel|null $group
     * @param bool                 $force
     */
    public function populate(array $fieldDefinition, FieldModel $field, $fieldHandle, FieldGroupModel $group = null, $force = false)
    {
        parent::populate($fieldDefinition, $field, $fieldHandle, $group);

        /** @var MatrixSettingsModel $settingsModel */
        $settingsModel = $field->getFieldType()->getSettings();
        $settingsModel->setAttributes($fieldDefinition['settings']);
        $settingsModel->setBlockTypes($this->getBlockTypes($fieldDefinition, $field, $force));
        $field->settings = $settingsModel;
    }

    /**
     * Get blocktypes.
     *
     * @param array      $fieldDefinition
     * @param FieldModel $field
     * @param bool       $force
     *
     * @return mixed
     */
    protected function getBlockTypes(array $fieldDefinition, FieldModel $field, $force = false)
    {
        $blockTypes = $this->getMatrixService()->getBlockTypesByFieldId($field->id, 'handle');

        //delete old blocktypes if they are missing from the definition.
        if ($force) {
            foreach ($blockTypes as $key => $value) {
                if (!array_key_exists($key, $fieldDefinition['blockTypes'])) {
                    unset($blockTypes[$key]);
                }
            }
        }

        foreach ($fieldDefinition['blockTypes'] as $blockTypeHandle => $blockTypeDef) {
            $blockType = array_key_exists($blockTypeHandle, $blockTypes)
              ? $blockTypes[$blockTypeHandle]
              : new MatrixBlockTypeModel();

            $blockType->fieldId = $field->id;
            $blockType->name = $blockTypeDef['name'];
            $blockType->handle = $blockTypeHandle;

            $this->populateBlockType($blockType, $blockTypeDef);

            $blockTypes[$blockTypeHandle] = $blockType;
        }

        return $blockTypes;
    }

    /**
     * Populate blocktype.
     *
     * @param BaseModel $blockType
     * @param array     $blockTypeDef
     */
    protected function populateBlockType(BaseModel $blockType, array $blockTypeDef)
    {
        $fieldFactory = $this->getFieldFactory();

        $blockTypeFields = [];
        foreach ($blockType->getFields() as $blockTypeField) {
            $blockTypeFields[$blockTypeField->handle] = $blockTypeField;
        }

        $newBlockTypeFields = [];

        foreach ($blockTypeDef['fields'] as $blockTypeFieldHandle => $blockTypeFieldDef) {
            $blockTypeField = array_key_exists($blockTypeFieldHandle, $blockTypeFields)
              ? $blockTypeFields[$blockTypeFieldHandle]
              : new FieldModel();

            $schematicFieldModel = $fieldFactory->build($blockTypeFieldDef['type']);
            $schematicFieldModel->populate($blockTypeFieldDef, $blockTypeField, $blockTypeFieldHandle);

            $newBlockTypeFields[] = $blockTypeField;
        }

        $blockType->setFields($newBlockTypeFields);
    }
}
