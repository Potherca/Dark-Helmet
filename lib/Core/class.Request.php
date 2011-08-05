<?php

namespace DarkHelmet\Core
{
    class Request {

////////////////////////////////// Properties \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		protected $m_sUrl = '';
	    protected $m_aPostFields = array();

////////////////////////////// Getters and Setters \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		public function setPostFields(Array $p_aPostFields)
	    {
		    $this->m_aPostFields = $p_aPostFields;
	    }

        /**
         * @return array
         */
	    public function getPostFields()
	    {
		    return $this->m_aPostFields;
	    }

	    public function setUrl($p_sUrl)
	    {
		    $this->m_sUrl = (string) $p_sUrl;
	    }

        /**
         * @return string
         */
		public function getUrl()
		{
			return $this->m_sUrl;
		}

        /**
         * @param $p_sUrl
         *
         * @return array
         */
		public function getParams()
		{
            $sUrl = $this->getUrl();
            return $this->getParamsFor($sUrl);
        }

        /**
         * @param $p_sUrl
         *
         * @return array
         */
		public function getParamsFor($p_sUrl)
		{
			$sUrl = strtolower($p_sUrl);
			$iStart = strpos($this->m_sUrl, $sUrl) + strlen($sUrl);
			$sParameters = substr($this->m_sUrl, $iStart);
			$aParameters = preg_split('#/#', $sParameters, null, PREG_SPLIT_NO_EMPTY);

			return $aParameters;
		}
//////////////////////////////// Public Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
	    /**
         * @static
         * @param $p_sUrl
         * @param array $p_aPostFields
         * @return \DarkHelmet\Core\Request
         */
        static public function get($p_sUrl, Array $p_aPostFields){
			$oInstance = new static;

			$oInstance->setUrl($p_sUrl);
			$oInstance->setPostFields($p_aPostFields);

			return $oInstance;
        }

        /**
         * @return string
         */
		public function __toString()
		{
			return (string) $this->getUrl();
		}


    }
}

#EOF