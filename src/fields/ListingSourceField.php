<?php
/**
 * Listing Source plugin for Craft CMS 3.x
 *
 * listing entries, categories, etc.
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\listingsource\fields;

use kuriousagency\listingsource\ListingSource;
use kuriousagency\listingsource\assetbundles\field\FieldAsset;
use kuriousagency\listingsource\assetbundles\settings\SettingsAsset;

use kuriousagency\listingsource\models\Category;
use kuriousagency\listingsource\models\Entry;
use kuriousagency\listingsource\models\Group;
use kuriousagency\listingsource\models\Products;
use kuriousagency\listingsource\models\Section;
use kuriousagency\listingsource\models\User;
use kuriousagency\listingsource\models\Bundle;
use kuriousagency\listingsource\models\Related;
use kuriousagency\listingsource\models\Event;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;
use craft\validators\ArrayValidator;

/**
 * @author    Kurious Agency
 * @package   ListingSource
 * @since     2.0.0
 */
class ListingSourceField extends Field
{
    // Public Properties
    // =========================================================================

	public $types;
	public $sources;

	// legacy
	public $selectLinkText;
	public $allowCustomText;
	public $defaultText;
	public $allowTarget;
	
	private $_availableTypes;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('listingsource', 'Listing Source');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
		//$rules[] = [['types'], ArrayValidator::class, 'min' => 1, 'tooFew' => Craft::t('listingsource', 'You must select one source type.'), 'skipOnEmpty' => false];
        return $rules;
	}

	public function validate($attributeNames = NULL, $clearErrors = true)
	{
		//Craft::dump($attributeNames);
		$sources = false;
		$return = true;
		foreach ($this->types as $key => $value)
		{
			if ($value['enabled']) {
				$sources = true;
				if ($value['sources'] == '') {
					$this->addError($key, 'Please select at least one source');
					$return = false;
				}
			}
		}
		if (!$sources) {
			$this->addError('sources', 'Please select a source');
			$return = false;
		}
		
		//Craft::dd($this->getErrors());
		return $return;
	}
	
	public function getElementValidationRules(): array
	{
		return ['validateValue'];
	}

	public function validateValue(ElementInterface $element)
	{
		$fieldValue = $element->getFieldValue($this->handle);
		
		//Craft::dd($fieldValue->validate());
        if($fieldValue && count($fieldValue->getErrors()))
        {
			//Craft::dump($fieldValue->getErrors());
            $element->addModelErrors($fieldValue, $this->handle);
		}
		//Craft::dd($fieldValue->getErrors());
	}

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
		//Craft::dump($value);
		if ($value instanceof Category ||
			$value instanceof Entry ||
			$value instanceof Group ||
			$value instanceof Products ||
			$value instanceof Section ||
			$value instanceof User ||
			$value instanceof Bundle ||
			$value instanceof Related ||
			$value instanceof Event
		) {
			
			return $value;
		}
		
		if (is_string($value)) {
			$value = Json::decodeIfJson($value);
		}

		$model = null;

		//Craft::dd($value);

		if (isset($value['type']) && $value['type'] != '') {

			$value['type'] = str_replace("kuriousagency\\listingsource\\models\\", '', $value['type']);
			$model = $this->getModelByType("kuriousagency\\listingsource\\models\\".$value['type'], $value);
			//Craft::dump($model->getParent());
		}
		//Craft::dump($value);

		return $model;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
		//Craft::dd($value);
		if ($value instanceof Category ||
			$value instanceof Entry ||
			$value instanceof Group ||
			$value instanceof Products ||
			$value instanceof Section ||
			$value instanceof User ||
			$value instanceof Bundle ||
			$value instanceof Related ||
			$value instanceof Event
		) {

				//Craft::dd($value->serializeValue($value, $element));
			return parent::serializeValue($value->serializeValue($value, $element), $element);
		}
		
		return parent::serializeValue($value, $element);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        $view = Craft::$app->getView();
		$view->registerAssetBundle(SettingsAsset::class);
		
        return $view->renderTemplate(
            'listingsource/_components/fields/settings',
            [
                'field' => $this,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
		/*if (!$value) {
			return '';
		}*/
		
		$view = Craft::$app->getView();
		
		// Register our asset bundle
        $view->registerAssetBundle(FieldAsset::class);

        // Get our id and namespace
        $id = $view->formatInputId($this->handle);
        $namespacedId = $view->namespaceInputId($id);

        // Variables to pass down to our field JavaScript to let it namespace properly
        $jsonVars = [
            'id' => $id,
            'name' => $this->handle,
            'namespace' => $namespacedId,
            'prefix' => $view->namespaceInputId(''),
            ];
        $jsonVars = Json::encode($jsonVars);
        $view->registerJs("$('#{$namespacedId}-field').ListingSourceField(" . $jsonVars . ");");

        // Render the input template
        return $view->renderTemplate(
            'listingsource/_components/fields/input',
            [
				'name' => $this->handle,
				'model' => $value,
				//'type' => $value['type'],
				//'value' => $value['value'],
				'element' => $element,
                'field' => $this,
                'id' => $id,
				'namespacedId' => $namespacedId,
            ]
        );
	}
	
	private function getModelByType(string $type, $value=null)
	{
		if (!$type) {
			return null;
		}
		$pluginsService = Craft::$app->getPlugins();
		$model = new $type();
		if ($value) {
			$model->setAttributes($value, false);
		}
		if ($model->type == 'Products' && (!$pluginsService->isPluginInstalled('commerce') || !$pluginsService->isPluginEnabled('commerce'))) {
			return null;
		}
		if ($model->type == 'Events' && (!$pluginsService->isPluginInstalled('events') || !$pluginsService->isPluginEnabled('events'))) {
			return null;
		}
		return $model;
	}

	public function getAllSourceTypes()
	{
		return ListingSource::$plugin->service->getSourceTypes();
	}

	public function getSourceTypes()
	{
		$types = [];
		//Craft::dd($this->types);

		foreach ($this->types as $key => $settings)
		{
			if ($settings['enabled']) {
				$type = $this->getModelByType($key, $settings);
				if ($type) {
					$types[] = $type;
				}
			}
		}

		return $types;
	}

	public function getSourceTypesAsOptions()
	{
		$options = [];
		$options[] = [
			'label' => 'Select source type',
			'value' => '',
		];
		
		foreach ($this->getSourceTypes() as $type)
		{
			$options[] = [
				'label' => $type->name,
				'value' => $type->type,
			];
		}
		return $options;
	}
}
