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
            case '{quickSearch}':
                return $this->renderQuickSearch();
            default:
                return false;
        }
    }

    public function renderLengthPicker()
    {
        $this->dataProvider->getPagination()->totalCount = $this->dataProvider->getTotalCount();
        $pagination = $this->dataProvider->getPagination();
        $currentSize = $pagination->pageSize;
        foreach ([10, 25, 50] as $value) {
            if ($value == $currentSize){
                $choices[] = '<li class="active"><a href="' . $pagination->createUrl($pagination->getPage(), $value) . '">' . $value . '</a></li>';
            }  else {
                $choices[] = '<li><a href="' . $pagination->createUrl($pagination->getPage(), $value) . '">' . $value . '</a></li>';
            }
        }
        return Html::tag('ul', implode("\n", $choices), ['class' => 'pagination pull-right']) . '<div class="pagination page-length-label pull-right">'
                . Yii::t('app', 'Items per page') . '</div>';
    }

    public function renderQuickSearch()
    {
        $placeholder = Yii::t('app', 'Search');
        $result = <<<HTML
<div class="input-group grid-quick-search" style="width: 200px;">
    <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
    <form data-pjax>
        <div id="indexGrid-filters">
            <input onkeyup="jQuery('#indexGrid').yiiGridView('applyFilter')"
                   class="form-control" id="quickSearchIndex" name="search" placeholder="$placeholder" type="text"/>
        </div>
    </form>
</div>
HTML;
        return $result;
    }

}
