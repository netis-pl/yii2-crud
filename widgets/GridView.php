<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\widgets;

use netis\utils\db\ActiveQuery;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;

/**
 * Extends \yii\grid\GridView, adding two new layout elements: lengthPicker and quickSearch.
 * @package netis\utils\widgets
 */
class GridView extends \yii\grid\GridView
{
    public $buttons = [];

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
        $this->dataProvider->getPagination()->totalCount = $this->dataProvider->getTotalCount();
        $pagination = $this->dataProvider->getPagination();
        $choices = [];
        foreach ([10, 25, 50] as $value) {
            $cssClass = $value === $pagination->pageSize ? 'active' : '';
            $url = $pagination->createUrl($pagination->getPage(), $value);
            $choices[] = '<li class="'.$cssClass.'"><a href="' . $url . '">' . $value . '</a></li>';
        }
        return Html::tag('ul', implode("\n", $choices), ['class' => 'pagination pull-right'])
            . '<div class="pagination page-length-label pull-right">' . Yii::t('app', 'Items per page') . '</div>';
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
    <form data-pjax>
        <div class="input-group grid-quick-search">
            <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
                <input class="form-control"
                    id="{$id}-quickSearch" name="search" placeholder="$placeholder" value="$value" type="text"/>
                     <!--onkeyup="jQuery('#{$id}').yiiGridView('applyFilter')"-->
        </div>
    </form>
HTML;
        return $result;
    }

}
