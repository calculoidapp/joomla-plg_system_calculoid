<?php
/**
 * @author         EasyJoomla.org
 * @subauthor      VikiJel
 * @copyright      ©2013 EasyJoomla.org
 * @license        http://opensource.org/licenses/LGPL-3.0 LGPL-3.0
 * @package        Joomla
 * @subpackage     Calculoid
 */
defined('_JEXEC') or die('Restricted access');

class plgSystemCalculoid extends JPlugin
{
	public function __construct($subject, $config)
	{
		parent::__construct($subject, $config);

		$this->calc_key       = trim($this->params->get('calc_key', 'demo2014'));
		$this->show_title     = (int) $this->params->get('show_title', 1);
		$this->show_desc      = (int) $this->params->get('show_description', 1);
		$this->run_in_be      = (int) $this->params->get('run_in_backend', 0);
		$this->load_framework = (int) $this->params->get('load_framework', 2);

		$this->loadLanguage();
	}

	public function onAfterInitialise()
	{
		if ($this->calc_key == '' or (!$this->run_in_be and JFactory::getApplication()->isAdmin()))
		{
			return;
		}

		// dont load if framework off or condicioned
		if ($this->load_framework == 0 or $this->load_framework == 2)
		{
			return;
		}

		$this->loadCalculoidFramework();
	}

	public function onAfterRender()
	{
		if ($this->calc_key == '' or (!$this->run_in_be and JFactory::getApplication()->isAdmin()))
		{
			return;
		}

		$attr  = ' ng-app="calculoid"';
		$body  = JResponse::getBody();
		$start = stripos($body, '<body');
		$end   = stripos($body, '>', $start);
		$body  = substr_replace($body, $attr, $end, 0);
		$tags  = [];
		$preg  = preg_match_all('/{calculoid ([0-9]*)([^}]*)}/', $body, $tags);

		if (!empty($tags[1]))
		{
			foreach ($tags[1] as $i => $calc_id)
			{
				$init_params = (object) [
					'calcId'          => $calc_id,
					'apiKey'          => $this->calc_key,
					'showTitle'       => $this->show_title,
					'showDescription' => $this->show_desc,
				];

				$tag_params     = trim($tags[2][$i]);
				$tag_params_arr = [];
				$init_values    = new \StdClass();

				preg_match_all('/([^\s\=\"]+)\=\"([^\"]*)\"/', $tag_params, $tag_params_arr);

				foreach ($tag_params_arr[1] as $j => $param_name)
				{
					$param_value = $tag_params_arr[2][$j];

					switch ($param_name)
					{
						case 'showTitle':
						case 'showDescription':
							$init_params->$param_name = $param_value;
							break;
						default:
							if (strpos($param_name, 'billing') === 0)
							{
								$billing_fieldname = substr($param_name, 8);

								if (!isset($init_values->billing))
								{
									$init_values->billing = new \StdClass();
								}

								$init_values->billing->$billing_fieldname = $param_value;
							}
							else
							{
								$init_values->$param_name = $param_value;
							}
							break;
					}
				}

				$init_params->values = $init_values;

				$html = '<div ng-controller="CalculoidMainCtrl" ng-init="init(' . htmlentities(json_encode($init_params)) . ')" ng-include="load()"></div>';
				$body = str_replace($tags[0][$i], $html, $body);
			}

			// also load framework if condicioned
			if ($this->load_framework == 2)
			{
				$body = $this->insertCalculoidFramework($body);
			}
		}

		JResponse::setBody($body);
	}

	public function loadCalculoidFramework()
	{
		if (!defined('PLG_SYSTEM_CALCULOID_ENABLED') || !PLG_SYSTEM_CALCULOID_ENABLED)
		{
			$calc_url      = trim($this->params->get('calc_url', 'https://embed.calculoid.com'));
			$calc_path_css = trim($this->params->get('calc_path_css', 'styles/main.css'));
			$calc_path_js  = trim($this->params->get('calc_path_js', 'scripts/combined.min.js'));
			$uri_js        = JUri::getInstance($calc_url);
			$document      = JFactory::getDocument();

			$uri_js->setPath('/' . $calc_path_js);
			$document->addScript($uri_js->toString());

			if ($this->params->get('use_css', 1))
			{
				$uri_css = JUri::getInstance($calc_url);
				$uri_css->setPath('/' . $calc_path_css);
				$document->addStyleSheet($uri_css->toString());
			}

			define('PLG_SYSTEM_CALCULOID_ENABLED', true);
		}
	}

	public function insertCalculoidFramework($body)
	{
		if (!defined('PLG_SYSTEM_CALCULOID_ENABLED') || !PLG_SYSTEM_CALCULOID_ENABLED)
		{
			$calc_url      = trim($this->params->get('calc_url', 'https://embed.calculoid.com'));
			$calc_path_css = trim($this->params->get('calc_path_css', 'styles/main.css'));
			$calc_path_js  = trim($this->params->get('calc_path_js', 'scripts/combined.min.js'));
			$uri_js        = JUri::getInstance($calc_url);

			$uri_js->setPath('/' . $calc_path_js);
			$tag_js = '<script src="' . $uri_js->toString() . '" type="text/javascript"></script>';
			$body   = str_replace("</head>", $tag_js . "\n</head>", $body);

			if ($this->params->get('use_css', 1))
			{
				$uri_css = JUri::getInstance($calc_url);
				$uri_css->setPath('/' . $calc_path_css);
				$tag_css = '<link rel="stylesheet" href="' . $uri_css->toString() . '" type="text/css"/>';
				$body    = str_replace("</head>", $tag_css . "\n</head>", $body);
			}

			define('PLG_SYSTEM_CALCULOID_ENABLED', true);
		}

		return $body;
	}
}
