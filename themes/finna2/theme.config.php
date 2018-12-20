<?php
return [
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'Finna\View\Helper\Root\Auth' => 'Finna\View\Helper\Root\AuthFactory',
            'Finna\View\Helper\Root\AuthorizationNotification' => 'Finna\View\Helper\Root\AuthorizationNotificationFactory',
            'Finna\View\Helper\Root\Autocomplete' => 'Finna\View\Helper\Root\AutocompleteFactory',
            'Finna\View\Helper\Root\Barcode' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\Browse' => 'Finna\View\Helper\Root\BrowseFactory',
            'Finna\View\Helper\Root\Callnumber' => 'Finna\View\Helper\Root\CallNumberFactory',
            'Finna\View\Helper\Root\CheckboxFacetCounts' => 'Finna\View\Helper\Root\CheckboxFacetCountsFactory',
            'Finna\View\Helper\Root\Citation' => 'Finna\View\Helper\Root\CitationFactory',
            'Finna\View\Helper\Root\Combined' => 'Finna\View\Helper\Root\CombinedFactory',
            'Finna\View\Helper\Root\Content' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\Cookie' => 'Finna\View\Helper\Root\CookieFactory',
            'Finna\View\Helper\Root\EDS' => 'Finna\View\Helper\Root\EDSFactory',
            'Finna\View\Helper\Root\Feed' => 'Finna\View\Helper\Root\FeedFactory',
            'Finna\View\Helper\Root\FileSrc' => 'Finna\View\Helper\Root\HelperWithThemeInfoFactory',
            'Finna\View\Helper\Root\FinnaSurvey' => 'Finna\View\Helper\Root\HelperWithMainConfigFactory',
            'FinnaTheme\View\Helper\HeadLink' => 'FinnaTheme\View\Helper\Factory::getHeadLink',
            'FinnaTheme\View\Helper\HeadScript' => 'FinnaTheme\View\Helper\Factory::getHeadScript',
            'FinnaTheme\View\Helper\InlineScript' => 'FinnaTheme\View\Helper\Factory::getInlineScript',
            'Finna\View\Helper\Root\HeadTitle' => 'Finna\View\Helper\Root\HelperWithMainConfigFactory',
            'Finna\View\Helper\Root\HoldingsSettings' => 'Finna\View\Helper\Root\HelperWithMainConfigFactory',
            'Finna\View\Helper\Root\ImageSrc' => 'Finna\View\Helper\Root\HelperWithThemeInfoFactory',
            'Finna\View\Helper\Root\LayoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClassFactory',
            'Finna\View\Helper\Root\Markdown' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\MetaLib' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\Navibar' => 'Finna\View\Helper\Root\NavibarFactory',
            'Finna\View\Helper\Root\OnlinePayment' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\OpenUrl' => 'Finna\View\Helper\Root\OpenUrlFactory',
            'Finna\View\Helper\Root\OrganisationDisplayName' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\OrganisationInfo' => 'Finna\View\Helper\Root\OrganisationInfoFactory',
            'Finna\View\Helper\Root\OrganisationsList' => 'Finna\View\Helper\Root\OrganisationsListFactory',
            'Finna\View\Helper\Root\PersonaAuth' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\Piwik' => 'VuFind\View\Helper\Root\PiwikFactory',
            'Finna\View\Helper\Root\Primo' => 'Finna\View\Helper\Root\PrimoFactory',
            'Finna\View\Helper\Root\ProxyUrl' => 'Finna\View\Helper\Root\ProxyUrlFactory',
            'Finna\View\Helper\Root\Recaptcha' => 'VuFind\View\Helper\Root\RecaptchaFactory',
            'Finna\View\Helper\Root\Record' => 'Finna\View\Helper\Root\RecordFactory',
            'Finna\View\Helper\Root\RecordDataFormatter' => 'Finna\View\Helper\Root\RecordDataFormatterFactory',
            'Finna\View\Helper\Root\RecordImage' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\RecordLink' => 'Finna\View\Helper\Root\RecordLinkFactory',
            'Finna\View\Helper\Root\ResultFeed' => 'VuFind\View\Helper\Root\ResultFeedFactory',
            'Finna\View\Helper\Root\ScriptSrc' => 'Finna\View\Helper\Root\HelperWithThemeInfoFactory',
            'Finna\View\Helper\Root\Search' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\SearchBox' => 'VuFind\View\Helper\Root\SearchBoxFactory',
            'Finna\View\Helper\Root\SearchMemory' => 'VuFind\View\Helper\Root\SearchMemoryFactory',
            'Finna\View\Helper\Root\SearchTabs' => 'Finna\View\Helper\Root\SearchTabsFactory',
            'Finna\View\Helper\Root\SearchTabsRecommendations' => 'Finna\View\Helper\Root\SearchTabsRecommendationsFactory',
            'Finna\View\Helper\Root\StreetSearch' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\Summon' => 'Finna\View\Helper\Root\SummonFactory',
            'Finna\View\Helper\Root\SystemMessages' => 'Finna\View\Helper\Root\SystemMessagesFactory',
            'Finna\View\Helper\Root\TotalIndexed' => 'Finna\View\Helper\Root\TotalIndexedFactory',
            'Finna\View\Helper\Root\Translation' => 'Finna\View\Helper\Root\TranslationFactory',
            'Finna\View\Helper\Root\TranslationEmpty' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\TruncateUrl' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'Finna\View\Helper\Root\UserAgent' => 'Finna\View\Helper\Root\UserAgentFactory',
            'Finna\View\Helper\Root\UserPublicName' => 'Zend\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'auth' => 'Finna\View\Helper\Root\Auth',
            'authorizationNote' => 'Finna\View\Helper\Root\AuthorizationNotification',
            'autocomplete' => 'Finna\View\Helper\Root\Autocomplete',
            'barcode' => 'Finna\View\Helper\Root\Barcode',
            'callnumber' => 'Finna\View\Helper\Root\Callnumber',
            'checkboxFacetCounts' => 'Finna\View\Helper\Root\CheckboxFacetCounts',
            'combined' => 'Finna\View\Helper\Root\Combined',
            'content' => 'Finna\View\Helper\Root\Content',
            'cookie' => 'Finna\View\Helper\Root\Cookie',
            'eds' => 'Finna\View\Helper\Root\EDS',
            'feed' => 'Finna\View\Helper\Root\Feed',
            'fileSrc' => 'Finna\View\Helper\Root\FileSrc',
            'finnaSurvey' => 'Finna\View\Helper\Root\FinnaSurvey',
            'headLink' => 'FinnaTheme\View\Helper\HeadLink',
            'headScript' => 'FinnaTheme\View\Helper\HeadScript',
            'inlineScript' => 'FinnaTheme\View\Helper\InlineScript',
            'headTitle' => 'Finna\View\Helper\Root\HeadTitle',
            'holdingsSettings' => 'Finna\View\Helper\Root\HoldingsSettings',
            //use root highlight so search results use span instead of mark
            'highlight' => 'VuFind\View\Helper\Root\Highlight',
            'imageSrc' => 'Finna\View\Helper\Root\ImageSrc',
            'indexedTotal' => 'Finna\View\Helper\Root\TotalIndexed',
            'markdown' => 'Finna\View\Helper\Root\Markdown',
            'metaLib' => 'Finna\View\Helper\Root\MetaLib',
            'navibar' => 'Finna\View\Helper\Root\Navibar',
            'onlinePayment' => 'Finna\View\Helper\Root\OnlinePayment',
            'organisationInfo' => 'Finna\View\Helper\Root\OrganisationInfo',
            'organisationDisplayName' => 'Finna\View\Helper\Root\OrganisationDisplayName',
            'organisationsList' => 'Finna\View\Helper\Root\OrganisationsList',
            'personaAuth' => 'Finna\View\Helper\Root\PersonaAuth',
            'primo' => 'Finna\View\Helper\Root\Primo',
            'recordImage' => 'Finna\View\Helper\Root\RecordImage',
            'scriptSrc' => 'Finna\View\Helper\Root\ScriptSrc',
            'search' => 'Finna\View\Helper\Root\Search',
            'searchbox' => 'Finna\View\Helper\Root\SearchBox',
            'searchMemory' => 'Finna\View\Helper\Root\SearchMemory',
            'searchTabsRecommendations' => 'Finna\View\Helper\Root\SearchTabsRecommendations',
            'streetSearch' => 'Finna\View\Helper\Root\StreetSearch',
            'systemMessages' => 'Finna\View\Helper\Root\SystemMessages',
            'translation' => 'Finna\View\Helper\Root\Translation',
            'translationEmpty' => 'Finna\View\Helper\Root\TranslationEmpty',
            'truncateUrl' => 'Finna\View\Helper\Root\TruncateUrl',
            'userAgent' => 'Finna\View\Helper\Root\UserAgent',
            'userPublicName' => 'Finna\View\Helper\Root\UserPublicName',

            // Overrides
            'VuFind\View\Helper\Root\Browse' => 'Finna\View\Helper\Root\Browse',
            'VuFind\View\Helper\Root\Citation' => 'Finna\View\Helper\Root\Citation',
            'VuFind\View\Helper\Root\OpenUrl' => 'Finna\View\Helper\Root\OpenUrl',
            'VuFind\View\Helper\Root\Piwik' => 'Finna\View\Helper\Root\Piwik',
            'VuFind\View\Helper\Root\ProxyUrl' => 'Finna\View\Helper\Root\ProxyUrl',
            'VuFind\View\Helper\Root\Recaptcha' => 'Finna\View\Helper\Root\Recaptcha',
            'VuFind\View\Helper\Root\Record' => 'Finna\View\Helper\Root\Record',
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'Finna\View\Helper\Root\RecordDataFormatter',
            'VuFind\View\Helper\Root\RecordLink' => 'Finna\View\Helper\Root\RecordLink',
            'VuFind\View\Helper\Root\ResultFeed' => 'Finna\View\Helper\Root\ResultFeed',
            'VuFind\View\Helper\Root\SearchTabs' => 'Finna\View\Helper\Root\SearchTabs',
            'VuFind\View\Helper\Root\Summon' => 'Finna\View\Helper\Root\Summon',
            'VuFind\View\Helper\Bootstrap3\LayoutClass' => 'Finna\View\Helper\Root\LayoutClass',
            'VuFind\View\Helper\Bootstrap3\Recaptcha' => 'Finna\View\Helper\Root\Recaptcha',

            // Aliases for non-standard cases
            'Combined' => 'combined',
            'KeepAlive' => 'keepAlive',
            'MetaLib' => 'metaLib',
            'metalib' => 'metaLib',
            'Primo' => 'primo',
            'proxyurl' => 'proxyUrl',
            'searchtabs' => 'searchTabs',
            'transesc' => 'transEsc',
            'inlinescript' => 'inlineScript',
        ]
    ],
    'css' => [
        'vendor/dataTables.bootstrap.min.css',
        'vendor/magnific-popup.min.css',
        'dataTables.bootstrap.custom.css',
        'vendor/slick.css',
        'vendor/slick-theme.css',
        'vendor/chosen.min.css',
        'vendor/bootstrap-datepicker3.min.css',
        'vendor/video-js.min.css',
        'vendor/bootstrap-slider.min.css',
        'finna.css',
        'vendor/priority-nav-core.css',
        'finna-flex-fallback.css::lt IE 10', // flex polyfill
        'vendor/leaflet.css',
        'vendor/leaflet.draw.css',
    ],
    'js' => [
        'vendor/event-stub.js:lt IE 9',
        'finna.js',
        'finna-autocomplete.js',
        'finna-combined-results.js',
        'finna-image-popup.js',
        'finna-adv-search.js',
        'finna-daterange-vis.js',
        'finna-feed.js',
        'finna-layout.js',
        'finna-openurl.js',
        'finna-common.js',
        'finna-map.js',
        'finna-map-facet.js',
        'finna-multiselect.js',
        'vendor/jquery.dataTables.min.js',
        'vendor/dataTables.bootstrap.min.js',
        'vendor/jquery.inview.min.js',
        'vendor/jquery.magnific-popup.min.js',
        'vendor/jquery.cookie-1.4.1.min.js',
        'vendor/slick.min.js',
        'vendor/video.min.js',
        'vendor/dash.all.min.js',
        'vendor/videojs-dash.min.js',
        'vendor/videojs-contrib-hls.min.js',
        'vendor/videojs.hotkeys.min.js',
        'vendor/jquery.touchSwipe.min.js',
        'vendor/bootstrap-slider.min.js',
        'vendor/gauge.min.js',
        'vendor/priority-nav.min.js',
        'vendor/leaflet.min.js',
        'vendor/leaflet.draw.min.js',
        'vendor/jquery.panzoom.min.js',
    ],
    'less' => [
        'active' => false
    ],
    'favicon' => 'favicon.ico',
];
