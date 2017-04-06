<?php

namespace NerdsAndCompany\Schematic\Models;

use Craft\Craft;
use Craft\FieldModel;
use Craft\FieldGroupModel;
use Craft\Neo_BlockTypeModel;
use Craft\Neo_GroupModel;

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

        /** @var \Craft\Neo_BlockTypeModel $blockType */
        foreach ($blockTypes as $blockType) {
            $blockTypeDefinitions[$blockType->handle] = [
              'name' => $blockType->name,
              'group' => !empty($groups) ? $groups[0]->name : null,
              'fieldLayout' => $this->getFieldsService()->getFieldLayoutDefinition($blockType->getFieldLayout()),
              'settings' => [
                'maxBlocks' => $blockType->maxBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'childBlocks' => $blockType->childBlocks,
                'topLevel' => $blockType->topLevel,
                'sortOrder' => $blockType->sortOrder,
              ]


              /*'id' => AttributeType::Number,
              'dateCreated' => AttributeType::DateTime,
              'dateUpdated' => AttributeType::DateTime,
              'fieldId' => AttributeType::Number,
              'fieldLayoutId' => AttributeType::String,
              'handle' => AttributeType::String */
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
        // Save the neo field itself
        // @OTOD: Check that all settings are saved.
        parent::populate($fieldDefinition, $field, $fieldHandle, $group);

        /** @var \Craft\Neo_SettingsModel $settingsModel */
        $settingsModel = $field->getFieldType()->getSettings();
        $settingsModel->setAttributes($fieldDefinition['settings']);
        $settingsModel->setGroups($this->getGroups($fieldDefinition, $field, $force));
        $settingsModel->setBlockTypes($this->getBlockTypes($fieldDefinition, $field, $force));
        $field->settings = $settingsModel;
    }

    protected function getGroups(array $fieldDefinition, FieldModel $field, $force = false)
    {
        // @TODO: When $force is true, look up groups by name and remove them if not in the export.
        $groups = [];
        if (isset($fieldDefinition['groups']) && is_array($fieldDefinition['groups'])) {
            foreach ($fieldDefinition['groups'] as $name => $values) {
                $group = new Neo_GroupModel();
                $group->setAttributes($values);
                $groups[] = $group;
                // @TODO: Dedupe by name
            }
        }

        return $groups;
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
        $blockTypes = $this->getNeoService()->getBlockTypesByFieldId($field->id, 'handle');

        // delete old blocktypes if they are missing from the definition.
        // @TODO does this work?
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
              : new Neo_BlockTypeModel();

            $blockType->fieldId = $field->id;
            $blockType->name = $blockTypeDef['name'];
            $blockType->handle = $blockTypeHandle;

            // Assign field layout
            $layout = $this->getFieldsService()->getFieldLayout($blockTypeDef['fieldLayout']);
            $blockType->setFieldLayout($layout);

            // Add settings
            $blockType->setAttributes($blockTypeDef['settings']);

            $blockTypes[$blockTypeHandle] = $blockType;
        }

        return $blockTypes;
    }
}
