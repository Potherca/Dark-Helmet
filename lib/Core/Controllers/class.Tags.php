<?php
namespace DarkHelmet\Core\Controllers
{
	use \SoapClient;
	use \SoapFault;

	use DarkHelmet\Core\Context;
	use DarkHelmet\Core\Settings;
	use DarkHelmet\Core\Hooks\Tags as TagsHook;

	class Tags extends Base implements TagsHook
	{
////////////////////////////////// Properties \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		protected $m_aTags = array();

////////////////////////////// Getters and Setters \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		public function setTags(Array $p_aTags){
			//@TODO: $this->validateTags($aTags);
			$this->m_aTags = $p_aTags;
		}
		public function getTags(Array $p_aTags){
			//@TODO: $aTags = array_merge_unique($aTags);
			return $this->m_aTags;
		}
//////////////////////////////// Public Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		public function __construct()
		{
			parent::__construct();
		}

		public function buildOutput()
		{
			//@TODO: The header "Content-Type" should not be set here, but marked in the response.
			header('Content-Type: text/plain;');
			$aTags = $this->array_unique_multi($this->m_aTags);

			return json_encode($aTags, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
		}

//////////////////////////////// Helper Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		protected function getPrefixes()
		{
			return $this->getSettings()->getPrefixes();
		}

#==============================================================================#
#   Utility methods: These still need serious refactoring!
#------------------------------------------------------------------------------#
		private function array_unique_multi($p_aArray)
		{
			$aUniqueArray = array();
			$aTempArray   = array();

			foreach($p_aArray as $t_iKey => $t_mValue) {
				$aTempArray[] = serialize($t_mValue);
			}#foreach

			$aTempArray = array_unique($aTempArray);

			foreach($aTempArray as $t_iKey => $t_mValue) {
				$aUniqueArray[] = unserialize($t_mValue);
			}#foreach

			return $aUniqueArray;
		}

		/**
		 * Replace underscores with an HTML entity and replace spaces with underscores
		 *
		 * @param string $p_sString
		 * @return string
		 */
		final static public function stripSpaces($p_sString)
		{
			$sReplacement       = '_';
			$sReplacementEntity = '&#95;';

			$sString = str_replace($sReplacement, $sReplacementEntity, $p_sString);
			$sString = str_replace(' ', $sReplacement, $p_sString);

			return $sString;
		}

		/**
		 * Replace underscores with spaces and replace underscore HTML entities with underscores.
		 *
		 * @param string $p_sString
		 * @return string
		 */
		final static public function unStripSpaces($p_sString)
		{
			$sReplacement       = '_';
			$sReplacementEntity = '&#95;';

			$sString = str_replace($sReplacement, ' ', $p_sString);
			$sString = str_replace($sReplacementEntity, $sReplacement, $sString);

			return $sString;
		}

		final static public function tagArray(Context $p_oContext, $p_sCategory, $p_sCaption, $p_sValue)
		{

			$aPrefixes = $p_oContext->get('aPrefix');
			if(array_key_exists($p_sCategory, $aPrefixes)){

				$sPrefix = $aPrefixes[$p_sCategory];
			}
			else{
				$sPrefix = $p_sCategory . ': ';
			}

			return array(
				  'caption' => $sPrefix . Tags::unStripSpaces($p_sCaption)
				, 'value' => $sPrefix . $p_sValue
				, 'addClass' => $p_sCategory
			);
		}
#==============================================================================#
	}
}