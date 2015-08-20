<?php
/**
 * @author    Patryk Radziszewski <pradziszewski@netis.pl>
 * @link      http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;

trait SaveFileTrait
{
    /**
     * @param string $fileClass Namespace of file class
     * @param array $files UploadedFile instances
     * @param $primaryValue Basic model primary
     * @param array $foreignKey ActiveQuery->link property
     *
     * @return bool
     */
    public function saveFile($fileClass, $files, $primaryValue, $foreignKey)
    {
        foreach ($files as $documentFile) {
            $document = new $fileClass();
            $content  = file_get_contents($documentFile->tempName);
            $info     = strpos($documentFile->type, 'image/') !== 0 ? [null, null] : getimagesizefromstring($content);
            $document->setAttributes([
                'filename'        => $documentFile->name,
                'size'            => $documentFile->size,
                'content'         => base64_encode($content),
                'mimetype'        => $documentFile->type,
                'hash'            => sha1($content),
                'width'           => $info[0],
                'height'          => $info[1],
                $foreignKey['id'] => $primaryValue,
            ], false);
            if (!$document->save()) {
                return false;
            }
        }

        return true;
    }
}