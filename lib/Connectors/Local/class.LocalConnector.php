<?php
namespace DarkHelmet\Connectors\Local
{
	use SplFileInfo;
	use SplFileObject;
	use DateTime;

	use DarkHelmet\Core\Context;
	use DarkHelmet\Core\Exception;
	use DarkHelmet\Core\Controllers\Tags;
	use DarkHelmet\Core\TimeLog;
	use DarkHelmet\Core\LogEntry;
    use DarkHelmet\Core\Controllers\Base as BaseController;

	use DarkHelmet\Connectors\Base;
	use DarkHelmet\Connectors\Hooks\History     as HistoryHook;
	use DarkHelmet\Connectors\Hooks\Init        as InitHook;
	use DarkHelmet\Connectors\Hooks\Persistence as PersistenceHook;
	use DarkHelmet\Connectors\Hooks\Tags        as TagsHook;

	/*
	 * The Local Connector provides the ability to read and write TimeLogs to
	 * disk locally (locally meaning on the same server as this Tool).
	 */
	class LocalConnector extends Base implements HistoryHook, InitHook, PersistenceHook, TagsHook
	{
////////////////////////////////// Properties \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		protected $m_aHistoryList;
		protected $m_oContext;

		protected $m_sFilePrefix = 'tags.';
		protected $m_sFileSuffix = '.log';
////////////////////////////// Getters and Setters \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		public function getHistoryList()
		{
			if(!isset($this->m_aHistoryList)){
				$aLogFiles = glob($this->getParam('LogsDir') . $this->m_sFilePrefix . '*'.$this->m_sFileSuffix);
				foreach($aLogFiles as $t_iIndex => $t_sLogPath){
					$aLogFiles[substr(basename($t_sLogPath), 5, 8)] = $t_sLogPath; // use date as index
					unset($aLogFiles[$t_iIndex]);
				}#foreach

				// Newest First
				krsort($aLogFiles);

				$this->m_aHistoryList = $aLogFiles;
			}#if

			return $this->m_aHistoryList;
		}

//////////////////////////////// Public Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		public function init(Context $p_oContext)
		{
			$this->m_oContext = $p_oContext;
			$this->populateTimeLogFromFile($p_oContext->get('oTimeLog'));
		}

		public function provideTags(Array $p_aTags, Context $p_oContext)
		{
			return $this->provideTagsFromHistory($p_aTags, $p_oContext);
		}

		/**
		 * @param \DarkHelmet\Core\Context $p_oContext
		 * @return \DarkHelmet\Core\Context
		 */
		//@FIXME: Currently provideHistory is abused to add history navigation to the main page. The functionality that does this should be refactored to a UI alteration function
		public function provideHistory(Context $p_oContext)
		{
            $aList = $this->getHistoryList();

            $aKeys = array_keys($aList);
            if(array_search($p_oContext->get('sToday'), $aKeys) === 0)
            {
	            $sCurrent = $p_oContext->get('sToday');
            }
            else
            {
                    $sCurrent = null;
            }

            $p_oContext->set('keys', $aKeys);

            /** @noinspection PhpUndefinedFieldInspection Field sBaseUrl is set in BaseController::getResponse() */
			$aParams = BaseController::getRequest()->getParamsFor($p_oContext->sBaseUrl);
            if(isset($aParams[1])){
                $sCurrent = $aParams[1];

                if($p_oContext->get('sToday') !== $sCurrent){

					$sDateString = $sCurrent;

					$oTimeLog = $this->createTimeLogFromDateString($sDateString, $p_oContext);

                    $p_oContext->set('oTimeLog', $oTimeLog);
                }#if
            }#if
            $p_oContext->set('current', $sCurrent);

            $sCurrentKey = array_search($sCurrent, $aKeys);

            //@TODO: Instead of setting the params here and having the buttons in the main template we should use the UI API once it has been implemented	BMP/2011/05/08

            if(isset($aKeys[$sCurrentKey+1])){
                $p_oContext->set('previous', 'history/' . $aKeys[ $sCurrentKey===false?0:$sCurrentKey+1 ]);
            }#if

            if(isset($aKeys[$sCurrentKey-1])){
                $p_oContext->set('next', $sCurrentKey-1 === 0 ?'':'history/' . $aKeys[$sCurrentKey-1]);
            }#if

            return $p_oContext;
		}

		/**
		 * @param $sDateString
		 * @param \DarkHelmet\Core\Context $p_oContext
		 *
		 * @return \DarkHelmet\Core\TimeLog
		 */
		public function createTimeLogFromDateString($sDateString, Context $p_oContext)
		{
			$oDate    = new DateTime($sDateString);
			return $this->createTimeLogFromDate($oDate, $p_oContext);
		}

		public function createTimeLogFromDate(DateTime $oDate, Context $p_oContext)
		{
			$oTimeLog = new TimeLog();
			$oTimeLog->setTagPrefixes($p_oContext->get('aPrefix'));
			$oTimeLog->setDate($oDate);
			$oTimeLog = $this->populateTimeLogFromFile($oTimeLog);

			return $oTimeLog;
		}

		public function provideTagsFromHistory(Array $p_aTags, Context $p_oContext)
		{
			$aParsedTags = $p_aTags;

//			$p_oContext->set('aLogFiles',$this->getHistoryList());

			// The code below provides tags from the history and should be moved
			// to a local-history-tag-provider-thingy.

			// What we need to do here is simply get the date for which we need
			// to show the history, validate a log file for it exists, read the
			// file into a TimeLog and build content from that.
			#==================================================================#
			#	    These should be part of the HistoryConnector
			#------------------------------------------------------------------#
			// How many days of log to put reference in the history and show on the page
			$iGoBack = $this->getParam('History');
			$sLogsDir = $this->getParam('LogsDir');

//			else if($p_oContext->get('sDate') !== $p_oContext->get('sToday') && !array_key_exists($p_oContext->get('sDate'), $this->m_aHistoryList)){
//				throw new \InvalidArgumentException('Could not find log for date "' . $p_oContext->get('sDate') . '"');
//			}

			$oToday = $p_oContext->getDate();
			$oFirstDate = new \DateTime(sprintf('-%d days midnight', $iGoBack));
			
			$aTags = array();

			/*
			 * Process the dates instead of the files, so the number of times we
			 * need to access the file system is based on the amount of days,
			 * not the amount of files.
			 */
			$oPeriod = new \DatePeriod($oFirstDate, new \DateInterval('P1D'), $iGoBack);
			$aLogs = array();
			foreach($oPeriod as $t_oDate) {
				/** @var $t_oDate DateTime */
				$sLogFile = $this->m_sFilePrefix . $t_oDate->format('Ymd') . $this->m_sFileSuffix;
				
				if(is_readable($sLogsDir . $sLogFile)) {
					// File is readable (and therefore does exists)
					$t_aContent = file($sLogsDir . $sLogFile, FILE_IGNORE_NEW_LINES);

					if($oToday == $t_oDate && count($t_aContent) >= 2) {
						$t_sTask = $t_aContent[count($t_aContent)-2];
						list(, $sLastTask) = explode(' ', $t_sTask, 2); // We're not interested in the first bit
					}#if

					$aLogs = array_merge($aLogs, $t_aContent);
				} else if(file_exists($sLogsDir . $sLogFile)) {
					// File does exist but is not readable.
					throw new Exception(sprintf('Logfile "%s" is not readable', $sLogFile));
				} else {
					// File does not exist. This is ok (it just means there is no log for that day)
					// so we can just skip this and continue.
				}#if
			}#foreach

			// Get all the tags and put them in the right category.
			foreach($aLogs as $t_sLog) {
				if($t_sLog !== '') {
					$t_aTags = explode(" ", $t_sLog);
					array_shift($t_aTags); // Remove the first element (the date)

					foreach($t_aTags as $t_sTag) {
						if(!empty($t_sTag) && ($sCategory = array_search($t_sTag{0}, $p_oContext->get('aPrefix'))) !== false) {
							$aTags[$sCategory][] = substr($t_sTag, 1);
						}
						else if(($sCategory = array_search('', $p_oContext->get('aPrefix'))) !== false) {
							$aTags[$sCategory][] = $t_sTag;
						}#if
					}#foreach
				}#if
			}#foreach

			// Remove duplicate values.
			foreach($aTags as $t_sCategory => $t_aTags) {
				$aTags[$t_sCategory] = array_unique($t_aTags);
			}#foreach

			// Organize the tags
			$aPrefixes = $p_oContext->get('aPrefix');

			foreach($aTags as $t_sCategory => $t_aTags){
				foreach($t_aTags as $t_sTag){
					$sCaption = Tags::unStripSpaces($t_sTag);

					$aParsedTags[] = array(
						  'caption' => $aPrefixes[$t_sCategory] . $sCaption
						, 'value' => $aPrefixes[$t_sCategory] . $t_sTag
						, 'addClass' => $t_sCategory
					);
				}#foreach
			}#foreach

			// Add last-but-one task as Previous Task Meta Tag
			//@TODO: We should filter meta tags out of the POST and fire logic for it there,
			//		 NOT in advance. That way, more meta tags could be added.
			if(isset($sLastTask)){
				$t_sCategory = 'Meta';
				$aParsedTags[] = array(
					  'caption' => $aPrefixes[$t_sCategory] . 'Previous Task'
					, 'value' => $aPrefixes[$t_sCategory] . $sLastTask
					, 'addClass' => $t_sCategory
				);
			}

			return $aParsedTags;
		}

		public function handlePersistenceFor(TimeLog $p_oTimeLog, Context $p_oContext)
		{
			$this->timeLogToFile($p_oTimeLog);
		}

//////////////////////////////// Helper Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		protected function filePathForTimeLog(TimeLog $p_oLogFile)
		{
			$sFilePath =
				  $this->getParam('LogsDir')
				. $this->m_sFilePrefix
				. $p_oLogFile->getDate()->format('Ymd')
				. $this->m_sFileSuffix
			;

			return $sFilePath;
		}

		protected function timeLogToFile(TimeLog $p_oTimeLog)
		{
			$sFilePath = $this->filePathForTimeLog($p_oTimeLog);

			if(!file_exists($sFilePath)){
				//Validate we have write access to the folder
				if(!is_writable(dirname($sFilePath))){
					throw new Exception('The log Folder is not writable!');
				}
				else {
					touch($sFilePath);
				}
			}
			
			// Now the file exists, validate we have write access to it
			if(!is_writable($sFilePath)) {
				throw new Exception(sprintf('The log file "%s" is not writable!', $sFilePath));
			}

			$oLogFile = new SplFileObject($sFilePath, 'w+');

			foreach($p_oTimeLog->getEntries() as $t_oEntry){
				/** @var $t_oEntry LogEntry */
				/** @noinspection PhpUndefinedMethodInspection Method format() is defined in the returned DateTime object class */
				$iWritten = $oLogFile->fwrite(
					  $t_oEntry->getTime()->format(DateTime::ATOM)
					. ' '
					. $t_oEntry->getMessage()
					. "\n"
				);

				if($iWritten < 1){
					throw new Exception('Nothing Written to LogFile.');
				}
			}#foreach

		}

		protected function populateTimeLogFromFile(TimeLog $p_oTimeLog)
		{
			$sFilePath = $this->filePathForTimeLog($p_oTimeLog);

			if(file_exists($sFilePath)){
				//@TODO: Validate we have read access
				$oLogFile = new SplFileObject($sFilePath, 'r');
				//@TODO: Merge data from current TimeLog and File
				$oLogFile->setFlags(SplFileObject::SKIP_EMPTY);

				while ($oLogFile->eof() === false){
					$sLine = trim($oLogFile->current());
					if(!empty($sLine)) {
						$p_oTimeLog->addEntry(LogEntry::fromString($sLine));
					}#if
					$oLogFile->next();
				}#while
			}else{
				// Nothing to add
			}#if

			return $p_oTimeLog;
		}

//////////////////////////////// Unused Methods \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
		/**
		 * @param \SplFileInfo $p_oLogFile
		 *
		 * @throws \InvalidArgumentException
		 * @return bool
		 */
		protected function validateFile(SplFileInfo $p_oLogFile)
		{
			/** @noinspection PhpUnusedLocalVariableInspection */
			$bValid = false;

			 try {
				 $sFileType = $p_oLogFile->getType();
			 }
			 catch (\RuntimeException $e) {
				throw new \InvalidArgumentException('Given Logfile "' . $p_oLogFile->getBasename() . '" does not exists.');
			 }#catch

			 if ($sFileType !== 'file') {
				throw new \InvalidArgumentException('Given Logfile "' . $p_oLogFile->getBasename() . '" is not a file but a ' . $sFileType . '.');
			 }
			 elseif ($p_oLogFile->isReadable() === false) {
				throw new \InvalidArgumentException('Given Logfile "' . $p_oLogFile->getBasename() . '" is not readable.');
			 }
			 elseif ($p_oLogFile->isWritable() === false) {
				throw new \InvalidArgumentException('Given Logfile "' . $p_oLogFile->getBasename() . '" is not writeable.');
			 }
			 else {
				$bValid = true;
			 }#if

			return $bValid;
		}
	}
}
#EOF
