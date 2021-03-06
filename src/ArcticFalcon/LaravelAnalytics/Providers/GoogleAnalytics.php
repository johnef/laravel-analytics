<?php
/**
 * mitarbeiterbereich2
 *
 * @author arcticfalcon
 * @since 1.1.0
 */

namespace ArcticFalcon\LaravelAnalytics\Providers;


use ArcticFalcon\LaravelAnalytics\Contracts\AnalyticsProviderInterface;
use ArcticFalcon\LaravelAnalytics\Contracts\TrackingBagInterface;
use ArcticFalcon\LaravelAnalytics\Data\Campaign;
use ArcticFalcon\LaravelAnalytics\Data\Event;
use ArcticFalcon\LaravelAnalytics\Data\Hit;
use ArcticFalcon\LaravelAnalytics\TrackingBag;

class GoogleAnalytics implements AnalyticsProviderInterface {

	/**
	 * tracking id
	 *
	 * @var string
	 */
	private $trackingId;

	/**
	 * tracking domain
	 *
	 * @var string
	 */
	private $trackingDomain;

	/**
	 * anonymize users ip
	 *
	 * @var bool
	 */
	private $anonymizeIp = false;

	/**
	 * auto tracking the page view
	 *
	 * @var bool
	 */
	private $autoTrack = false;

	/**
	 * session tracking bag
	 *
	 * @var TrackingBag
	 */
	private $trackingBag;

	private $nonInteraction;

	private $sandbox = false;
	
	private $customDimensions = [];

	protected $enableDisplayFeatures = false;

	/**
	 * setting options via constructor
	 *
	 * @param array $options
	 * @throws \InvalidArgumentException when tracking id not set
	 */
	public function __construct(array $options = array(), TrackingBagInterface $trackingBag = null)
	{
		$this->trackingId = (isset($options['tracking_id'])) ? $options['tracking_id'] : null;
		$this->trackingDomain = (isset($options['tracking_domain'])) ? $options['tracking_domain'] : 'auto';
		$this->anonymizeIp = (isset($options['anonymize_ip'])) ? $options['anonymize_ip'] : false;
		$this->autoTrack = (isset($options['auto_track'])) ? $options['auto_track'] : false;
		$this->sandbox = (isset($options['sandbox'])) ? $options['sandbox'] : false;
		$this->enableDisplayFeatures = (isset($options['display_features'])) ? $options['display_features'] : false;

		if ($this->trackingId === null)
		{
			throw new \InvalidArgumentException('Argument tracking_id can not be null');
		}

		$this->trackingBag = $trackingBag?: new TrackingBag;
	}

	/**
	 * track an page view
	 *
	 * @param null|string $page
	 * @param null|string $title
	 * @param null|string $hittype
	 * @return void
	 */
	public function trackPage($page = null, $title = null, $hittype = null)
	{
		$allowedHitTypes = ['pageview', 'appview', 'event', 'transaction', 'item', 'social', 'exception', 'timing'];
		if ($hittype === null)
		{
			$hittype = $allowedHitTypes[0];
		}

		if (!in_array($hittype, $allowedHitTypes))
		{
			return;
		}

		$trackingCode = "ga('send', 'pageview');";

		if ($page !== null || $title !== null || $hittype !== null)
		{
			$page = ($page === null) ? "window.location.protocol + '//' + window.location.hostname + window.location.pathname + window.location.search" : "'{$page}'";
			$title = ($title === null) ? "document.title" : "'{$title}'";

			$trackingCode = "ga('send', {'hitType': '{$hittype}', 'page': {$page}, 'title': '{$title}'});";
		}

		$this->trackingBag->add($trackingCode);
	}


	/**
	 * track an event
	 *
	 * @param string $category
	 * @param string $action
	 * @param null|string $label
	 * @param null|int $value
	 */
	public function trackEvent($category, $action, $label = null, $value = null)
	{
		$command = '';
		if ($label !== null)
		{
			$command .= ", '{$label}'";
			if ($value !== null && is_numeric($value))
			{
				$command .= ", {$value}";
			}
		}

		$trackingCode = "ga('send', 'event', '{$category}', '{$action}'$command);";

		$this->trackingBag->add($trackingCode);
	}

	/**
	 * track any custom code
	 *
	 * @param string $customCode
	 * @return void
	 */
	public function trackCustom($customCode)
	{
		$this->trackingBag->add($customCode);
	}

	public function trackHit(Hit $hit)
	{
		$this->trackingBag->add($hit->render());
	}

	/**
	 * enable auto tracking
	 *
	 * @return void
	 */
	public function enableAutoTracking()
	{
		$this->autoTrack = true;
	}

	/**
	 * disable auto tracking
	 *
	 * @return void
	 */
	public function disableAutoTracking()
	{
		$this->autoTrack = false;
	}

	/**
	 * set Non-interaction hit
	 *
	 * @return void
	 */
	public function nonInteraction($value)
	{
		$this->nonInteraction = boolval($value);
	}


	public function setCustomDimension($index, $value)
	{
		if(!is_int($index) || $index < 1 || $index > 200)
		{
			throw new \InvalidArgumentException("index must be a positive integer between 1 and 200");
		}

		$this->customDimensions[$index] = $value;
	}

	/**
	 * @return boolean
	 */
	public function isEnableDisplayFeatures()
	{
		return $this->enableDisplayFeatures;
	}

	/**
	 * @param boolean $enableDisplayFeatures
	 */
	public function setEnableDisplayFeatures($enableDisplayFeatures)
	{
		$this->enableDisplayFeatures = $enableDisplayFeatures;
	}


	/**
	 * returns the javascript embedding code
	 *
	 * @return string
	 */
	public function render()
	{
		$script[] = $this->_getJavascriptTemplateBlockBegin();

		if ($this->sandbox)
		{
			$script[] = "ga('create', '{$this->trackingId}', { 'cookieDomain': 'none' });";
		}
		else
		{
			$script[] = "ga('create', '{$this->trackingId}', '{$this->trackingDomain}');";
		}

		if ($this->enableDisplayFeatures)
		{
			$script[] = "ga('require', 'displayfeatures');";
		}

		if ($this->anonymizeIp)
		{
			$script[] = "ga('set', 'anonymizeIp', true);";
		}

		if($this->nonInteraction)
		{
			$script[] = "ga('set', 'nonInteraction', true);";
		}

		foreach($this->customDimensions as $index => $customDimension)
		{
			$script[] = "ga('set', 'dimension$index', '$customDimension');";
		}

		$trackingStack = $this->trackingBag->get();
		if (count($trackingStack))
		{
			$script[] = implode("\n", $trackingStack);
		}

		if ($this->autoTrack)
		{
			$script[] = "ga('send', 'pageview');";
		}
		$script[] = $this->_getJavascriptTemplateBlockEnd();

		return  implode('', $script);
	}

	/**
	 * returns start block
	 *
	 * @return string
	 */
	protected function _getJavascriptTemplateBlockBegin()
	{
		$debug = $this->sandbox? '_debug' : '';
		return "<script>(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics{$debug}.js','ga');";
	}

	/**
	 * returns end block
	 *
	 * @return string
	 */
	protected function _getJavascriptTemplateBlockEnd()
	{
		return '</script>';
	}

	/**
	 * assembles an url for tracking measurement without javascript
	 *
	 * e.g. for tracking email open events within a newsletter
	 *
	 * @param string $metricName
	 * @param mixed $metricValue
	 * @param \ArcticFalcon\LaravelAnalytics\Data\Event $event
	 * @param \ArcticFalcon\LaravelAnalytics\Data\Campaign $campaign
	 * @param string|null $clientId
	 * @param array $params
	 * @return string
	 *
	 * @experimental
	 */
	public function trackMeasurementUrl($metricName, $metricValue, Event $event, Campaign $campaign, $clientId = null, array $params = array())
	{
		$uniqueId = ($clientId !== null) ? $clientId : uniqid('track_');

		if ($event->getLabel() === '')
		{
			$event->setLabel($uniqueId);
		}

		if ($campaign->getName() === '')
		{
			$campaign->setName('Campaign ' . date('Y-m-d'));
		}

		$defaults = [
			'url' => 'http://www.google-analytics.com/collect?',
			'params' => [
				'v' => 1,	//	protocol version
				'tid' => $this->trackingId,	//	tracking id
				'cid' => $uniqueId,	//	client id
				't' => $event->getHitType(),
				'ec' => $event->getCategory(),
				'ea' => $event->getAction(),
				'el' => $event->getLabel(),
				'cs' => $campaign->getSource(),
				'cm' => $campaign->getMedium(),
				'cn' => $campaign->getName(),
				$metricName => $metricValue,	//	metric data
			],
		];

		$url = isset($params['url']) ? $params['url'] : $defaults['url'];
		$url = rtrim($url, '?') . '?';

		if (isset($params['url']))
			unset($params['url']);

		$params = array_merge($defaults['params'], $params);
		$queryParams = [];
		foreach ($params as $key => $value)
		{
			if (!empty($value))
				$queryParams[] = sprintf('%s=%s', $key, $value);
		}

		return $url . implode('&', $queryParams);
	}
}
