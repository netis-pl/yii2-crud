<?php

namespace netis\utils\widgets;

use Yii;
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
            case '{summary}': {
                    $this->summaryOptions = ['class' => 'summary text-center'];
                    return Html::tag('div class ="col-md-4 summary"', $this->renderSummary());
                }
            case '{items}': {
                    return Html::tag('div class ="col-md-12"', $this->renderItems());
                }
            case '{pager}': {
                    return Html::tag('div class ="col-md-4"', Html::tag('div class ="pull-left"', $this->renderPager()));
                }
            case '{sorter}':
                return $this->renderSorter();
            case '{lengthPicker}':
                return Html::tag('div class ="col-md-4"', $this->renderLengthPicker());
            default:
                return false;
        }
    }

    public function renderLengthPicker()
    {
        $this->dataProvider->getPagination()->totalCount = $this->dataProvider->getTotalCount();
        $pagination = $this->dataProvider->getPagination();
        foreach ([10, 25, 50] as $value) {
            $choices[] = '<li><a href="' . $pagination->createUrl($pagination->getPage(), $value) . '">' . $value . '</a></li>';
        }
        return Html::tag('ul', implode("\n", $choices), ['class' => 'pagination pull-right']) . '<div class="pagination page-length-label pull-right">'
                . Yii::t('app', 'Items per page') . '</div>';
    }

}
