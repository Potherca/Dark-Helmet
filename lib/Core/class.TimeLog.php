<?php

namespace DarkHelmet\Core
{
	use \DateTime;
	use \InvalidArgumentException;

	use DarkHelmet\Core\Context;

    class TimeLog {
////////////////////////////////// Properties \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		protected $m_oContext;
		protected $m_oDate;

	    private $m_aEntries  = array();
	    private $m_aPrefixes = array();

////////////////////////////// Getters and Setters \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
	    /**
	     * @return array
	     */
	    public function getEntries()
	    {
		    $this->sortByDate();
		    return $this->m_aEntries;
	    }

	    /**
	     * @param string $name
	     * @param string $value
	     */
	    public function addEntry(LogEntry $p_oEntry)
	    {
		    $this->m_aEntries[] = $p_oEntry;
	    }

	    /**
	     * @param string $name
	     */
	    public function getEntry($name)
	    {
		    $result = null;
		    if (isset($this->m_aEntries[$name]))
		    {
			    $result = $this->m_aEntries[$name];
		    }
		    return $result;
	    }

	    /**
	     * @return void
	     */
	    public function setTagPrefixes(Array $p_aPrefixes)
	    {
		    $this->m_aPrefixes = $p_aPrefixes;
	    }

	    /**
	     * @return array
	     */
	    public function getTagPrefixes()
	    {
		    return $this->m_aPrefixes;
	    }

	    /**
	     * @return array
	     */
	    public function setDate(DateTime $p_oDate)
	    {
		    return $this->m_oDate = $p_oDate;
	    }

	    /**
	     * @return array
	     */
	    public function getDate()
	    {
		    return $this->m_oDate;
	    }

//////////////////////////////// Public Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
	    public function toString()
	    {
		    $sThis = '';
		    foreach($this->getEntries() as $t_iTime => $t_oEntry)
		    {
			    $sThis .= $t_oEntry->toString() ."\n";
		    }#foreach
		    return $sThis;
	    }

	    public function toHtml()
	    {
		    $sHtml = '';

		    $oDate = $this->getDate();
			if($oDate !== null){
				$sHtml .= '<h2>' . $oDate->format('D d M') . '</h2>';
			}#if


		    $sHtml .= '<ul class="TimeLog">' ."\n";

		    $aEntries = $this->getEntries();
		    foreach($aEntries as $t_iTime => $t_oEntry)
		    {
			    $aPrefixes = $this->getTagPrefixes();
			    $sHtml .= '<li>'
				    . '<span class="time">'
				    . $t_oEntry->getTime()->format('H:i')
				    . '</span>'
				    . ' '
			    ;
			    foreach(explode(' ', $t_oEntry->getMessage()) as $t_sMessage){
				    $sClass = null;
				    $sMessage =  LogEntry::unstripspaces($t_sMessage);
				    if(!empty($sMessage) && in_array($sMessage{0}, $aPrefixes)){
					    $sClass = array_search($sMessage{0}, $aPrefixes);
					    $sMessage = substr($sMessage, 1);
				    }

				    $sHtml .= '<span' . (isset($sClass)?' class="'.$sClass.'"':'') . '>';

				    //@TODO: This should be fixed by allowing Plugins to register tag-ui alteration rules...    BMP/21/12/2011
				    if($sClass === 'Ticket' && strpos($t_oEntry->getMessage(), '~JiraConnector') !==false)
				    {
					    $sMessage = '<a href="https://intern.vrestmedical.com/jira/browse/' . $sMessage. '" target="_BLANK">' . $sMessage. '</a>';
				    }
				    
				    $sHtml .= $sMessage;
				    $sHtml .= ' </span>';
				    ;
			    }

			    //$sHtml .= $t_oEntry->toString() ."\n";
			    $sHtml .= '</li>'."\n";
		    }#foreach
		    $sHtml .= '</ul>'."\n";
		    return $sHtml;
	    }

	    public function __toString()
	    {
		    return $this->toString();
	    }

	    public function getTotalTime(array $p_aTotals)
	    {
		    $aTotals = $p_aTotals;

		    foreach($this->getEntries() as $t_iIndex => $t_oEntry){

			    $oNext = $this->getEntry($t_iIndex + 1);
			    if($oNext !== null){

				    $aEntryTags = $this->getTagsFromEntry($t_oEntry);

				    foreach($aEntryTags as $key => $value){
					    if($value === 'User'){
						    unset($aEntryTags[$key]);
					    }#if
				    }#foreach

				    if(!empty ($aEntryTags)){
					    asort($aEntryTags);
					    $sUniqueTaskName = implode('|',array_values($aEntryTags)) . "\0" . implode('|', array_keys($aEntryTags));

					    $oDateInterval = $t_oEntry->getTime()->diff($oNext->getTime());

					    // Tally Totals per Line
					    if(!isset($aTotals[$sUniqueTaskName])){
						    $aTotals[$sUniqueTaskName] = new DateTime('@0');
					    }#if
					    $aTotals[$sUniqueTaskName]->add($oDateInterval);
				    }#if
			    }#if
		    }#foreach

		    return $aTotals;
	    }

	    public function outputTaskTotalTime()
	    {
		    $aTotals = array(
			    'ALL' => array()
		    );

		    //$this->calculateTotals();
		    foreach($this->getEntries() as $t_iIndex => $t_oEntry){

			    $oNext = $this->getEntry($t_iIndex + 1);
			    if($oNext !== null){
				    $taskname = $t_oEntry->getMessage();

				    //@TODO: Add "ignore tags" attribute
				    if(strpos($taskname, '%PAUSE') === false){
					    $oDateInterval = $t_oEntry->getTime()->diff($oNext->getTime());

					    // Tally Totals per Line
					    if(!isset($aTotals['ALL'][$taskname])){
						    $aTotals['ALL'][$taskname] = new DateTime('@0');
					    }#if
					    $aTotals['ALL'][$taskname]->add($oDateInterval);

					    if(!isset($aTotals['ALL']['__TOTAL__'])){
						    $aTotals['ALL']['__TOTAL__'] = new DateTime('@0');
					    }#if
					    $aTotals['ALL']['__TOTAL__']->add($oDateInterval);

					    // Tally Totals per Tag Type
					    foreach($this->getTagsFromEntry($t_oEntry) as $t_sTagName => $t_sTagType){
						    if(!isset($aTotals[$t_sTagType])){
							    $aTotals[$t_sTagType] = array();
							    $aTotals[$t_sTagType]['__TOTAL__'] = new DateTime('@0');
						    }#if

						    if(!isset($aTotals[$t_sTagType][$t_sTagName])){
							    $aTotals[$t_sTagType][$t_sTagName] = new DateTime('@0');
						    }#if

						    $aTotals[$t_sTagType][$t_sTagName]->add($oDateInterval);
						    $aTotals[$t_sTagType]['__TOTAL__']->add($oDateInterval);
					    }#foreach
				    }#if
			    }#if
		    }#foreach

		    return $this->htmlTotals($aTotals);
	    }

	    public function htmlTotals(Array $p_aTotals)
	    {
		    $sContent = '';

		    $iCounter = 0;
		    $sTabs = '';
		    foreach($p_aTotals as $t_sTagType => $t_aTotals){
			    $iCounter++;
			    $sSectionContent = '<ul class="TimeLog">' . "\n";
			    $sTabs .= '<li><a href="#tabs-'.$iCounter.'">'.$t_sTagType.'</a></li>' . "\n";

			    arsort($t_aTotals);
			    foreach($t_aTotals as $t_sTask => $t_oTime){
				    if($t_sTask === '__TOTAL__'){
					    $oSectionTotal = $t_oTime;
				    }else{
					    $aDate = getdate($t_oTime->format('U'));
					    $sSectionContent .= '<li>'
						    . '<span class="time">'
						    .  ($aDate['hours'] - 1 < 10?'0':'') . ($aDate['hours'] - 1) . ':'
						    .  ($aDate['minutes'] < 10?'0':'') . $aDate['minutes'] . ':'
						    .  ($aDate['seconds'] < 10?'0':'') . $aDate['seconds']
						    . '</span>'
						    . '<span class="'.$t_sTagType.'">'
						    . $t_sTask
						    . '</span>'
						    .'</li>' . "\n"
					    ;
				    }#if
			    }#foreach
			    $sSectionContent.= '</ul>' . "\n";

			    if(isset ($oSectionTotal)){
				    $aSectionTotal = getdate($oSectionTotal->format('U'));
				    $sContent .=
					      '<div id="tabs-'.$iCounter.'">' . "\n"
					    .	$sSectionContent
					    .	'<div style="clear:both;">'
					    .		($aSectionTotal['hours'] - 1 < 10?'0':'') . ($aSectionTotal['hours'] - 1) . ':'
					    .		($aSectionTotal['minutes'] < 10?'0':'') . $aSectionTotal['minutes'] . ':'
					    .		($aSectionTotal['seconds'] < 10?'0':'') . $aSectionTotal['seconds']
					    .	'</div>' . "\n"
					    . '</div>' . "\n"
				    ;
			    }#if
		    }#foreach

		    if(empty($sContent)){
			    $sContent = '<i>no data available</i>';
		    }else{
			    $sContent = '<ul>' . $sTabs . '</ul>' . "\n" . $sContent;
		    }#if

		    return $sContent;
	    }

	    static public function htmlUniqueTotals(Array $p_aTotals)
	    {
		    $aContent = array();

		    foreach($p_aTotals as $t_sTask => $t_oTime){
			    $aDate = getdate($t_oTime->format('U'));
			    $aContent[''
				    .  ($aDate['hours'] - 1 < 10?'0':'') . ($aDate['hours'] - 1) . ':'
				    .  ($aDate['minutes'] < 10?'0':'') . $aDate['minutes'] . ':'
				    .  ($aDate['seconds'] < 10?'0':'') . $aDate['seconds']
				    ] = $t_sTask
			    ;
		    }#foreach

		    if(empty($aContent)){
			    $sContent = array('<i>no data available</i>');
		    }else{

			    $sContent  = '<ul class="TimeLog">';
			    krsort($aContent);
			    foreach($aContent as $sTime => $t_sTask){
				    $t_sTask;

				    $sContent .= '<li>'
					    . '<span class="time">'
					    . $sTime
					    . '</span>';

				    list($sTagTypes, $sTasks) = explode("\0", $t_sTask);

				    foreach(
					    array_combine(
						      array_values(explode('|', $sTagTypes))
						    , array_values(explode('|', $sTasks))
					    ) as $t_sTagType => $t_sTask
				    ){
					    $sContent .= ' <span class="' . $t_sTagType . '">'
						    . $t_sTask
						    . '</span>'
					    ;
				    }#foreach
					    $sContent .= '</li>'. "\n"
				    ;
			    }
			    $sContent .= '</ul>' . "\n";
		    }#if

		    return $sContent;
	    }

//////////////////////////////// Helper Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
	    protected function getTagsFromEntry(LogEntry $p_oEntry)
	    {
		    $aTags = array();

		    $aPrefixes = $this->getTagPrefixes();

		    foreach(explode(' ', $p_oEntry->getMessage()) as $t_sTag){
			    if(!empty($t_sTag) && in_array($t_sTag{0}, $aPrefixes)){
				    $sTagType = array_search($t_sTag{0}, $aPrefixes);
				    $sKey = substr(LogEntry::unstripspaces($t_sTag), 1);
				    $aTags[$sKey] = $sTagType;
			    }#if
		    }#foreach

		    return $aTags;
	    }

	    protected function sortByDate()
	    {
			return usort($this->m_aEntries, function($p_a1, $p_a2){
				$iOrder = 0;

				$i1 = $p_a1->getTime()->format('U');
				$i2 = $p_a2->getTime()->format('U');

				if ($i1 !== $i2) {
					$iOrder = ($i1 < $i2) ? -1 : 1;
				}#if

				return $iOrder;
			});
	    }
	}
}

#EOF