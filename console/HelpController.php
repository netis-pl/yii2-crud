<?php

namespace netis\crud\console;

use Yii;
use yii\console\Controller;

/**
 * Description here...
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
class HelpController extends \yii\console\controllers\HelpController
{
    /**
     * Returns an array of commands an their descriptions.
     * @return array all available commands as keys and their description as values.
     */
    protected function getCommandDescriptions()
    {
        $descriptions = [];
        foreach ($this->getCommands() as $command) {
            $result = Yii::$app->createController($command);
            if ($result === false) {
                continue;
            }

            list($controller, $actionID) = $result;

            if (!($controller instanceof Controller)) {
                continue;
            }
            /** @var Controller $controller */
            $descriptions[$command] = $controller->getHelpSummary();
        }

        return $descriptions;
    }
}