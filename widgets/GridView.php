<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\widgets;

use netis\crud\db\ActiveQuery;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;

/**
 * Extends \yii\grid\GridView, adding two new layout elements: lengthPicker and quickSearch.
 * @package netis\crud\widgets
 */
class GridView extends \yii\grid\GridView
{
    public $buttons = [];
    public $lengthPickerOptions = ['class' => 'pagination pull-right'];

    public function init()
    {
        parent::init();
    }

    private $clientOptions = [];

    /**
     * @inheritdoc
     */
    public function renderSection($name)
    {
        if (parent::renderSection($name) !== false) {
            return parent::renderSection($name);
        }
        switch ($name) {
            case '{buttons}':
                return $this->renderButtons();
            case '{lengthPicker}':
                return $this->renderLengthPicker();
            case '{quickSearch}':
                return $this->renderQuickSearch();
            default:
                return false;
        }
    }

    /**
     * Renders toolbar buttons.
     * @return string the rendering result
     */
    public function renderButtons()
    {
        return implode('', $this->buttons);
    }

    /**
     * Renders the page length picker.
     * @return string the rendering result
     */
    public function renderLengthPicker()
    {
        $pagination = $this->dataProvider->getPagination();
        if ($pagination === false || $this->dataProvider->getCount() <= 0) {
            return '';
        }
        $pagination->totalCount = $this->dataProvider->getTotalCount();
        $choices = [];
        foreach ([10, 25, 50] as $value) {
            $cssClass = $value === $pagination->pageSize ? 'active' : '';
            $url = $pagination->createUrl($pagination->getPage(), $value);
            $choices[] = '<li class="'.$cssClass.'"><a href="' . $url . '">' . $value . '</a></li>';
        }
        return Html::tag('ul', implode("\n", $choices), $this->lengthPickerOptions);
    }

    /**
     * Renders the quick search input field.
     * @return string the rendering result
     */
    public function renderQuickSearch()
    {
        if (!$this->dataProvider instanceof ActiveDataProvider || !$this->dataProvider->query instanceof ActiveQuery) {
            return '';
        }
        $id = $this->getId();
        $value = Html::encode($this->dataProvider->query->quickSearchPhrase);
        $placeholder = Yii::t('app', 'Search');
        $result = <<<HTML
        <div class="form-group">
            <div class="input-group grid-quick-search">
                <div class="input-group-btn">
                    <button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i></button>
                </div>
                <input class="form-control"
                        id="{$id}-quickSearch" name="search[search-term]" placeholder="$placeholder" value="$value" type="text"/>
                         <!--onkeyup="jQuery('#{$id}').yiiGridView('applyFilter')"-->
                <div class="input-group-btn">
                    <button class="btn btn-default" onclick="$('#{$id}-quickSearch').val('').change();return false;">
                        <i class="glyphicon glyphicon-remove"></i>
                    </button>
                </div>
            </div>
        </div>
HTML;
        return $result;
    }

    public function setClientOptions($options) {
        $this->clientOptions = $options;
    }

    protected function getClientOptions()
    {
        return array_merge($this->clientOptions, parent::getClientOptions());
    }
}
