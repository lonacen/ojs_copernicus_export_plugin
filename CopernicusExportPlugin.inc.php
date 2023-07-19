<?php

/**
 * @file plugins/importexport/copernicus/CopernicusExportPlugin.inc.php
 *
 * Copyright (c) 2018 Oleksii Vodka
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportPlugin
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

class CopernicusExportPlugin extends ImportExportPlugin
{
    /**
     * Called as a plugin is registered to the registry
     * @param $category String Name of category plugin was registered to
     * @return boolean True iff plugin initialized successfully; if false,
     *    the plugin will not be registered.
     */
    function register($category, $path, $mainContextId = NULL)
    {
        $success = parent::register($category, $path, $mainContextId);
        // Additional registration / initialization code
        // should go here. For example, load additional locale data:
        $this->addLocaleData();
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
        $this->addLocaleData();

        // This is fixed to return false so that this coding sample
        // isn't actually registered and displayed. If you're using
        // this sample for your own code, make sure you return true
        // if everything is successfully initialized.
        // return $success;
        return $success;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    function getName()
    {
        // This should not be used as this is an abstract class
        return 'CopernicusExportPlugin';
    }

    function getDisplayName()
    {
        return __('plugins.importexport.copernicus.displayName');
    }

    function displayName()
    {
        return 'Copernicus export plugin';
    }

    function getDescription()
    {
        return __('plugins.importexport.copernicus.description');
    }

    function createNode(&$document, &$parent, $name, $attributes = [], $content = ''): DOMElement
    {
        $node = $document->createElement($name);
        $parent->appendChild($node);

        if (is_array($attributes)) {
            foreach ($attributes as $attribute => $value) {
                $node->setAttribute($attribute, $value);
            }
        }

        $node->textContent = $content;

        return $node;
    }

    function formatDate($date)
    {
        if ($date == '')
            return null;
        return date('Y-m-d', strtotime($date));
    }

    function multiexplode($delimiters, $string)
    {

        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return $launch;
    }

    function &generateIssueDom(&$journal, &$issue)
    {
        $journalId = $journal->getId();
        $journalIssn = $journal->getSetting('printIssn');
        $journalIssn = $journalIssn ? $journalIssn : $journal->getSetting('onlineIssn');

        $issueId = $issue->getId();
        $issueNumber = $issue->getNumber();
        $issueVolume = $issue->getVolume();
        $issueYear = $issue->getYear();
        $issuePublishedDate = DateTime::createFromFormat('Y-m-d H:i:s', $issue->getDatePublished())->format('Y-m-d');

        $coverImageUrl = "";
        $coverImages = $issue->getData('coverImage');
        foreach ($coverImages as $coverImage) {
            if(empty($coverImage)) {
                continue;
            }
            $request = Application::get()->getRequest();

	    	import('classes.file.PublicFileManager');
		    $publicFileManager = new PublicFileManager();

		    $coverImageUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($journalId) . '/' . $coverImage;
            break;
        }

        $document = new DOMDocument('1.0', 'utf-8');

        $rootNode = $this->createNode(
            $document,
            $document,
            'ici-import'
        );

        $journalNode = $this->createNode(
            $document,
            $rootNode,
            'journal',
            [
                "issn" => $journalIssn
            ]
        );

        $issueNodeAttributes = [
            "number" => $issueNumber,
            "volume" => $issueVolume,
            "year" => $issueYear,
            "publicationDate" => $issuePublishedDate
        ];

        if(!empty($coverImageUrl)) {
            $issueNodeAttributes["coverUrl"] = $coverImageUrl;
            $issueNodeAttributes["coverDate"] = $issuePublishedDate;
        }

        $issueNode = $this->createNode(
            $document,
            $rootNode,
            'issue',
            $issueNodeAttributes
        );

        $sectionDao =& DAORegistry::getDAO('SectionDAO');
        $submissionKeywordDao =& DAORegistry::getDAO('SubmissionKeywordDAO');

        $articlesCount = 0;

        $issueSubmissions = iterator_to_array(Services::get('submission')->getMany([
            'contextId' => $journalId,
            'issueIds' => [$issueId],
            'status' => STATUS_PUBLISHED,
            'orderBy' => 'seq',
            'orderDirection' => 'ASC',
        ]));

        foreach ($issueSubmissions as $article) {
            $publication = $article->getCurrentPublication();

            $title = $publication->getData('title');
            if (!$title) {
                continue;
            }

            $locales = array_keys(PKPLocale::getSupportedFormLocales());

            $articleNode = $this->createNode(
                $document,
                $issueNode,
                'article'
            );

            $typeNode = $this->createNode(
                $document,
                $articleNode,
                'type',
                [],
                'ORIGINAL_ARTICLE'
            );

            foreach ($locales as $locale) {
                $iso1Locale = PKPLocale::getIso1FromLocale($locale);

                $languageVersionNode = $this->createNode(
                    $document,
                    $articleNode,
                    'languageVersion',
                    [
                        "language" => $iso1Locale
                    ]
                );

                $titleNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'title',
                    [],
                    $publication->getLocalizedTitle($locale)
                );

                $abstractNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'abstract',
                    [],
                    strip_tags($publication->getLocalizedData('abstract', $locale))
                );

                $url = "";
                foreach ($publication->getData('galleys') as $galley) {
                    if (!$galley->getRemoteURL() && $galley->isPdfGalley()) {
                        $request = Application::get()->getRequest();
                        $url = $request->url($journal->getPath(), "article", "download", array($article->getBestId(), $galley->getBestGalleyId()), null, null, true);
                    }
                    break;
                }

                $pdfFileUrlNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'pdfFileUrl',
                    [],
                    $url
                );


                $articlePublicationDate = DateTime::createFromFormat('Y-m-d', $article->getDatePublished())->format('Y-m-d');

                $publicationDateNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'publicationDate',
                    [],
                    $articlePublicationDate
                );

                $pageFromNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'pageFrom',
                    [],
                    $publication->getStartingPage()
                );

                $pageToNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'pageTo',
                    [],
                    $publication->getEndingPage()
                );

                $doiNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'doi',
                    [],
                    $publication->getStoredPubId('doi')
                );

                $keywordsNode = $this->createNode(
                    $document,
                    $languageVersionNode,
                    'keywords',
                );

                $keywords = $submissionKeywordDao->getKeywords($publication->getId(), array($locale));
                if ($keywords)
                    $keywords = $keywords[$locale];
                $j = 0;
                foreach ($keywords as $keyword) {
                    $keywordNode = $this->createNode(
                        $document,
                        $keywordsNode,
                        'keyword',
                        [],
                        $keyword
                    );
                    $j++;
                }

                if ($j == 0) {
                    $keywordNode = $this->createNode(
                        $document,
                        $keywordsNode,
                        'keyword'
                    );
                }
            }

            $authorsNode = $this->createNode(
                $document,
                $articleNode,
                'authors'
            );

            $authorsCount = 1;
            $authorsLocale = PKPLocale::getLocalePrecedence();
            foreach ($publication->getData('authors') as $author) {

                $authorNode = $this->createNode(
                    $document,
                    $authorsNode,
                    'author'
                );

                $familyNames = $author->getFamilyName(null);
                $givenNames = $author->getGivenName(null);

                $authorGivenName = htmlspecialchars($givenNames[$authorsLocale[0]], ENT_COMPAT, 'UTF-8');
                $authorSurname = htmlspecialchars($familyNames[$authorsLocale[0]], ENT_COMPAT, 'UTF-8');

                $authorNameNode = $this->createNode(
                    $document,
                    $authorNode,
                    'name',
                    [],
                    $authorGivenName
                );

                $authorSurnameNode = $this->createNode(
                    $document,
                    $authorNode,
                    'surname',
                    [],
                    $authorSurname
                );

                $authorEmailNode = $this->createNode(
                    $document,
                    $authorNode,
                    'email',
                    [],
                    $author->getEmail()
                );

                $authorOrderNode = $this->createNode(
                    $document,
                    $authorNode,
                    'order',
                    [],
                    $authorsCount
                );

                $authorInstituteAffiliationNode = $this->createNode(
                    $document,
                    $authorNode,
                    'instituteAffiliation',
                    [],
                    substr($author->getLocalizedAffiliation(), 0, 250)
                );

                $authorRoleNode = $this->createNode(
                    $document,
                    $authorNode,
                    'role',
                    [],
                    'AUTHOR'
                );

                $authorORCIDNode = $this->createNode(
                    $document,
                    $authorNode,
                    'ORCID',
                    [],
                    $author->getData('orcid')
                );

                $authorsCount++;
            }

            if (method_exists($article, "getLocalizedCitations")) {
                $citationText = $article->getLocalizedCitations();
            } else {
                $citationText = $article->getCitations();
            }

            if ($citationText) {
                $citationParts = explode("\n", $citationText);

                $referencesNode = $this->createNode(
                    $document,
                    $articleNode,
                    'references'
                );

                $citationsCount = 1;
                foreach ($citationParts as $citation) {
                    if (empty(trim($citation))) {
                        continue;
                    }

                    $referenceNode = $this->createNode(
                        $document,
                        $referencesNode,
                        'reference'
                    );

                    $unparsedContentNode = $this->createNode(
                        $document,
                        $referenceNode,
                        'unparsedContent',
                        [],
                        $citation
                    );

                    $orderNode = $this->createNode(
                        $document,
                        $referenceNode,
                        'order',
                        [],
                        $citationsCount
                    );

                    $doiNode = $this->createNode(
                        $document,
                        $referenceNode,
                        'doi'
                    );

                    $citationsCount++;
                }
            }

            $articlesCount++;
        }

        $issueNode->setAttribute('numberOfArticles', $articlesCount);

        return $document;
    }

    function display($args, $request)
    {
        parent::display($args, $request);
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        switch (array_shift($args)) {
            case '':
            case 'index':
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;

            case 'exportIssues':
                import('lib.pkp.classes.file.FileManager');
                $fileManager = new FileManager();
                $journal =& $request->getJournal();
                $issueDao = DAORegistry::getDAO('IssueDAO');
                $issueIds = (array) $request->getUserVar('selectedIssues');
                foreach ($issueIds as $issueId) {
                    $issue = $issueDao->getById($issueId, $context->getId());
                    if ($issue) {
                        libxml_use_internal_errors(true);
                        $document = $this->generateIssueDom($journal, $issue);
                        $document->formatOutput = true;
                        $exportXml = $document->saveXML();
                        $errors = array_filter(libxml_get_errors(), function ($a) {
                            return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
                        });
                        if (!empty($errors)) {
                            $this->displayXMLValidationErrors($errors,$exportXml);
                        }

                        $issueNumber = $issue->getNumber();
                        $issueVolume = $issue->getVolume();
                        $issueYear = $issue->getYear();

                        $filenamePart = $issueYear . "-" . $issueVolume . "-" . $issueNumber;

                        $exportFileName = $this->getExportFileName($this->getExportPath(), $filenamePart, $context, '.xml');
                        $fileManager->writeFile($exportFileName, $exportXml);
                        $fileManager->downloadByPath($exportFileName);
                        $fileManager->deleteByPath($exportFileName);
                    }
                }
                break;

            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();

        }
    }

    /**
     * Execute import/export tasks using the command-line interface.
     * @param $args Parameters to the plugin
     */
    function executeCLI($scriptName, &$args)
    {
        $this->usage($scriptName);
    }

    /**
     * Display the command-line usage information
     */
    function usage($scriptName)
    {
        echo "USAGE NOT AVAILABLE.\n";
    }

    function getPluginSettingsPrefix() {
		return 'copernicus';
	}
}