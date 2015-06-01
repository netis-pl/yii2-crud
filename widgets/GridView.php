<?php

namespace netis\utils\widgets;

use yii\helpers\ArrayHelper;
use yii\widgets\LinkPager;
use yii\data\Pagination;
use yii\helpers\Html;

/**
 *
 *
 *
 */
class GridView extends \yii\grid\GridView
{

    public function renderSection($name)
    {
        switch ($name) {
            case "{errors}":
                return $this->renderErrors();
            case '{summary}':
                return $this->renderSummary();
            case '{items}':
                return $this->renderItems();
            case '{pager}':
                return $this->renderPager();
            case '{sorter}':
                return $this->renderSorter();
            case '{lengthPicker}':
                return $this->renderLengthPicker();
            default:
                return false;
        }
    }

    public function renderLengthPicker()
    {
        $this->dataProvider->getPagination()->totalCount = $this->dataProvider->getTotalCount();
        $pagination = $this->dataProvider->getPagination();
        $choices[] = Html::tag('li', Html::a('10', $pagination->createUrl($pagination->getPage(), 10)));
        $choices[] = Html::tag('li', Html::a('25', $pagination->createUrl($pagination->getPage(), 25)));
        $choices[] = Html::tag('li', Html::a('50', $pagination->createUrl($pagination->getPage(), 50)));
        $options = $this->options;
        $options['id'] = 'w0_length';
        $options['class'] = 'pagination pull-right';
        return Html::tag('ul', implode("\n", $choices), $options);
    }
}
