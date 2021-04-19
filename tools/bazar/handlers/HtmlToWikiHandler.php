<?php

use YesWiki\Core\YesWikiHandler;
use YesWiki\Bazar\Service\EntryManager;

class HtmlToWikiHandler extends YesWikiHandler
{
    protected $entryManager;

    public function run()
    {
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        // user is admin ?
        if (!$this->wiki->UserIsAdmin()) {
            // not connected
            return $this->twig->renderInSquelette('@templates/alert-message-with-back.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS') . ' (htmltowiki)'
            ]);
        }

        if (
            $this->wiki->page
            && null !== $entry = $this->entryManager->getOne($this->wiki->page['tag'])
        ) {
            $content = $entry['bf_contenu'];

            dump($content);

            $content = $this->replace($content);

            $content = preg_replace(
                [
                    '/""(\s*)""/U',
                    '/(\r\n\s*){3,}/',
                ], [
                    '$1',
                    "\r\n\r\n",
                ],
                $content
            );

            dump($content);

            return;
        }

        return "Handler HTMLtoWiki only runs on entries";
    }

    private function replace($content)
    {
        $parts = explode('""', $content);

        for ($i=1; $i < count($parts); $i+=2) { # For html parts
            $part = $parts[$i];
            
            if ($htmlPos = strpos($part, '<') === false) continue;

            // Parse HTML ?
            $part = preg_replace(
                [
                    '/<p[\s\S]+>/U',
                    '#</p>#',
                    '#<span[\s\S]+>|</span>#U',
                ], [
                    '',
                    "\r\n\r\n",
                    '',
                ],
                $part
            );

            if ($htmlPos = strpos($part, '<') !== false) {
                $part = '""' . $part . '""';
            }

            $parts[$i] = $part;
        }

        return implode(' ', $parts);
    }
}
