<?php
/*
*******************************************************************************/

// Only reachable through YesWiki
if (!defined("WIKINI_VERSION"))
{
	 die ("accès direct interdit");
}

// For admins eyes only!
$isAdmin = $this->UserIsAdmin();
if (!$isAdmin){
echo '<div class="alert alert-danger alert-error">'._t('ADMINS_ONLY_ACTION').'</div>';
return ;
}

// functions definition
if (!function_exists('setNotFound'))
{
	function setNotFound(&$filesDisplay, $file)
	{
		$filesDisplay[$file]['bazar'] = false;
		$filesDisplay[$file]['media'] = null;	// _t('MEDIA_UNABLE_TO_UNDERSTAND_FILE_NAME')
	}
}

if (!function_exists('setPagesInfilesDisplay'))
{
	function setPagesInfilesDisplay(&$filesDisplay, &$remainingFilesToTags, $file, $page, $media, $time, $extension, $latestTags)
	{
		$filesDisplay[$file]['bazar'] = false;
		$filesDisplay[$file]['media'] = $media;
		$filesDisplay[$file]['page'] = $page;
		if ($remainingFilesToTags[$media]['page'] == $page) { // What the RegExp found is consistent with what was found in DB
			$filesDisplay[$file]['extension'] = $extension;
			$filesDisplay[$file]['time'] = $time; // In some cases, it's not upload time, but page version creation time
			$filesDisplay[$file]['pageIsActive'] = true;
			$filesDisplay[$file]['additionalPageText'] = '';
			$filesDisplay[$file]['pageIsLatest'] = true; // _t('MEDIA_USED_ON_PAGE_LATEST_VERSION')
			unset($remainingFilesToTags[$media]); // Suppress that file from the array
		} else { // Unconsistency between regexp and DB
			$filesDisplay[$file]['extension'] = '';
			$filesDisplay[$file]['time'] = '';
			if (array_search($page,$latestTags,true)) { // Page exists in its latest version
				$filesDisplay[$file]['pageIsActive'] = true;
			} else {
				$filesDisplay[$file]['pageIsActive'] = false;
			}
			$filesDisplay[$file]['additionalPageText'] = '';
			$filesDisplay[$file]['pageIsLatest'] = false; // _t('MEDIA_UNUSED_ON_PAGE_LATEST_VERSION')
		}
	}
}

if (!function_exists('buildMediaAndPage'))
{
	function buildMediaAndPage(&$filesDisplay, &$remainingFilesToTags, $file, $fileName, $date, $extension, $latestTags)
	{
		$fileNameBits = explode("_", $fileName);
		$bitsNumber = count($fileNameBits);
		$i = $bitsNumber - 1;
		$trialMediaName = $fileNameBits[$i]; // build a media name form $fileNameBits (starting at the end)
		$media = '';
		$page = '';
		$found = false;
		do {
			if (array_key_exists($trialMediaName, $remainingFilesToTags)) { // there is a media with that name
				$remainingString = $fileNameBits[0]; // a page name form $fileNameBits (starting at the beginning)
				for ($j=1; $j < $i; $j++) {
					$remainingString .= '_'.$fileNameBits[$j];
				}
				$media .= $trialMediaName.'<br/>'; // Whatever happens on next test, this value is concatenated with preceding ones
				$page .= $remainingString.'<br/>'; // Whatever happens on next test, this value is concatenated with preceding ones
				if ($remainingFilesToTags[$trialMediaName] == $remainingString) { // The page name for that media is correct (Perfect match)
					$found = true;
					$media = $trialMediaName; // Perfect match => replace the temp value
					$page = $remainingString; // Perfect match => replace the temp value
					unset($remainingFilesToTags[$trialMediaName]); // Suppress that file from the array
					$i = 0; // Job finished, Let's get out
				}
			} // End of there is a media with that name
			$i--;
			$trialMediaName = $fileNameBits[$i].'_'.$trialMediaName;
		} while ($i > 0);
		// Here, we have three cases.
		// 1. We found a perfect match (media and page) and can set $filesDisplay
		// 2. We found at least one matching media name and we stored the last we found ($media) as weel as the corresponding, wrong, page ($page). We know it's not correct but that is information
		// 3. We found nothing that matches
		if ($found){ // Perfect match (media AND page)
			setPagesInfilesDisplay($filesDisplay, $remainingFilesToTags, $file, $page, $media, $date, $extension, $latestTags);
		} elseif ($media == '') { // Neither perfect match, nor any consistent media name
			setNotFound($filesDisplay, $file);
		} else { // Found some consistent media names and taking all of them along with the coresponding page names
			$filesDisplay[$file]['bazar'] = false;
			$filesDisplay[$file]['media'] = $media;
			$filesDisplay[$file]['page'] = $page;
			$filesDisplay[$file]['extension'] = $extension;
			$filesDisplay[$file]['time'] = $time;
			$filesDisplay[$file]['pageIsActive'] = false;
			$filesDisplay[$file]['additionalPageText'] = '';
			$filesDisplay[$file]['pageIsLatest'] = false;
		}
	}
}
// End of functions definition

// Handling of submits (when one click a 'media_delete' button)
if ($isAdmin && (!empty($_POST['media_delete']))) { // Check if the page received a post named 'media_delete'
$file = $_POST['media_delete'];
//	$OK = unlink('files/'.$file);
unlink('files/'.$file);
// if (!$OK) {
// 	die (_t('MEDIA_UNABLE_TO_DELETE_FILE'));
// }
$GLOBALS["wiki"]->redirect($GLOBALS["wiki"]->href());
}
// End of handling of submits

// Some titles and explanations for the user
echo '<h3>'._t('MEDIA_FILES_DIR_LISTING').'</h3>', "\n";
echo '<h4>'._t('MEDIA_EXPLANATIONS').'</h4>';
echo '<p>'._t('MEDIA_FILE').' => '._t('MEDIA_FILE_EXPLANATION').'</p>';
echo '<p>'._t('MEDIA_NAME').' => '._t('MEDIA_NAME_EXPLANATION').'</p>';
echo '<p>'._t('MEDIA_UPLOAD_TIME').' => '._t('MEDIA_UPLOAD_TIME_EXPLANATION').'</p>';
echo '<p>'._t('MEDIA_PAGE').' => '._t('MEDIA_PAGE_EXPLANATION').'</p>';
echo '<p>'._t('MEDIA_PAGE_VERSION').' => '._t('MEDIA_PAGE_VERSION_EXPLANATION').'</p>';
echo '<p>'._t('MEDIA_VERSION').' => '._t('MEDIA_VERSION_EXPLANATION').'</p>';
echo '<h4>'._t('MEDIA_NOTICES').'</h4>';
// End of titles and explanations for the user

// Preparing files table display
$tablePrefix = $GLOBALS["wiki"]->config['table_prefix'];
$pagesTable = $tablePrefix.'pages';
$triplesTable = $tablePrefix.'triples';

/* Build $filesToRecords
	an array of media referenced by bazar records
	file => array(page, field name, field type)
*/
$sql = 'SELECT tag, body FROM '.$pagesTable.' WHERE latest = "Y" AND tag IN (SELECT resource FROM '.$triplesTable.' WHERE value = "fiche_bazar")';
$bazarRecords = $GLOBALS["wiki"]->LoadAll($sql);
$filesToRecords=array(array());
if (!is_null($bazarRecords) && (count($bazarRecords) > 0)){
	foreach ($bazarRecords as $bazarRecord) { // ($bazarRecord['tag'],$bazarRecord['body'])
		if (preg_match_all('/,\"(data-)?(fichier|image)([^\"]+)\":\"([^\"]*)\"/', $bazarRecord['body'], $files)) {
			for ($i = 0; $i < count($files[0]); $i++) { // $files contains all successive matches to parenthesized subpatters
				// $files[0][$i] contains the entire search pattern
				// $files[1][$i] contains the first subpattern: "data-" or "" (we don't care)
				// $files[2][$i] contains the second subpattern: this is the field type
				// $files[3][$i] contains the third subpattern: this is the field name
				// $files[4][$i] contains the fourth subpattern: this is the field value, ie file name
				if ($files[4][$i] != '') {
					// file.ext = array(page, field, field type)
					$filesToRecords[$files[4][$i]] = array($bazarRecord['tag'], $files[3][$i], $files[2][$i]);
				}
			}
		}
	}
}/* else {
	die(_t('MEDIA_UNABLE_TO_RETRIEVE_BAZAR_RECORDS'));
}*/
// End of $filesToRecords building

/* Build $filesToTags
	an array of media referenced by {{attach}} actions in wiki pages
	file (without extension) => array(page, extension)
*/
$sql = 'SELECT tag, body FROM '.$pagesTable.' WHERE body LIKE "%{{attach%" AND `latest` = "Y"'; // }}
$pageRecords = $GLOBALS["wiki"]->LoadAll($sql);
$filesToTags=array(array()); // file (without extension) => array(page, extension)
if (isset($pageRecords) && (count($pageRecords) > 0)) {
	foreach ($pageRecords as $pageRecord) { // ($pageRecord['tag'],$pageRecord['body'])
		if (preg_match_all('/\{\{attach.*?file=\\\?"([^"]+)\\\?"[^\}]*\}\}/', $pageRecord['body'], $attaches)) {
			foreach ($attaches[1] as $file) { // attaches['1'] contains all successive matches to parenthesized subpattern
				if (($file != '') && ($file != ' ')) {
					$fileName = explode(".", $file);
					$filesToTags[$fileName[0]] = array('page' => $pageRecord['tag'], 'extension' => $fileName[1]);
				} else {
					echo '<p>'._t('MEDIA_FOLLOWING_PAGE_CONTAINS_EMPTY_ATTACH').' : '.$pageRecord['tag'].'.</p>';
				}
			}
		} else { // Strange! no matches in a record SQL found as matching!
			die(_t('MEDIA_ERROR_NO_MATCHES_IN_PAGES'));
		}
	} // End foreach ($pageRecords as $pageRecord)
} else {
	die(_t('MEDIA_UNABLE_TO_RETRIEVE_ATTACH_PAGES'));
}
// End of $filesToTags building

/* Build $latestTags
	an array of wiki pages in their latest version
*/
$sql = 'SELECT tag FROM '.$pagesTable.' WHERE latest = "Y" AND tag IN (SELECT resource FROM '.$triplesTable.' WHERE (value != "fiche_bazar") AND (value != "liste"))';
$latestTags = $GLOBALS["wiki"]->LoadAll($sql);
if (!isset($latestTags) || !(count($latestTags) > 0)) {
	die(_t('MEDIA_UNABLE_TO_RETRIEVE_LATEST_PAGES'));
}
// End of $latestTags building

/* Build $filesDisplay
	an array of all files in the "files" directory with the required info to display
	file => (
		'bazar' => ,
		'media' => ,
		'page' => ,
		'extension => ,
		'time' => ,
		'pageIsActive' => ,
		'additionalPageText' => ,
		'pageIsLatest' => ,
		'latestPageVersionText' => ,
		'MediaIsLatest' => ,
		'latestMediaVersionText' => ,
		'deleteColText' => ,
	)
*/
$remainingFilesToTags = $filesToTags;
$filesDisplay = array(array());
$filesVersions = array(array()); // file => ('page' => , 'media' => , 'time' => , 'last' =>)
foreach(glob('files/*.*') as $file) {
	$arr = explode("/", $file);
	$file = $arr[1];
	$RegExpOK = true;
	if (array_key_exists($file, $filesToRecords)) { // If the file is referenced by a bazar record
		$filesDisplay[$file]['bazar'] = true;
		$filesDisplay[$file]['media'] = $file;
		$filesDisplay[$file]['page'] = $filesToRecords[$file][0];
		$filesDisplay[$file]['additionalPageText'] = ' Champ = '.$filesToRecords[$file][1];
	// Beware, some parenthesized subpatterns are ungreedy (there is a question mark)
	} elseif (preg_match('`^([^_]+)_([^_]+)_\d{14}_(\d{14}).*\.(.*)`', $file, $match)) { // Two dates
		setPagesInfilesDisplay($filesDisplay, $remainingFilesToTags, $file, $match[1], $match[2], $match[3], $match[4], $latestTags);
	} elseif (preg_match('`^([^_]+)_([^_]+)_(\d{14}).*\.(.*)`', $file, $match)) { // Only one date
		setPagesInfilesDisplay($filesDisplay, $remainingFilesToTags, $file, $match[1], $match[2], $match[3], $match[4], $latestTags);
	} elseif (preg_match('`^(.*?)_vignette_(\d{3}_)\2(\d{14}).*\.(.*)`', $file, $match)) { // look for the first date (page time) => 2nd subpattern, and what's before => 1st subpattern
		// Both previous regexp were ungreedy
		// therefore, we are going to search $remainingFilesToTags for a corresponding media name using the $fileNameBits we have.
		buildMediaAndPage($filesDisplay, $remainingFilesToTags, $file, $match[1], $match[3], $match[4], $latestTags);
	} elseif (preg_match('`^(.*?)_(\d{14}).*\.(.*)`', $file, $match)) { // Same as previous, without the vignette bit
		buildMediaAndPage($filesDisplay, $remainingFilesToTags, $file, $match[1], $match[2], $match[3], $latestTags);
	} else { // Unable to find anything
		setNotFound($filesDisplay, $file);
	}

	// For media attached to latest version of active pages, set $filesVersions
	if (!($filesDisplay[$file]['bazar']) && ! is_null($filesDisplay[$file]['media'])) {
	// We have a real page (no bazar record and no problem)
		if ($filesDisplay[$file]['pageIsActive'] && $filesDisplay[$file]['pageIsLatest']) {
		// Media used on the latest version of an active page
			$filesVersions[$file] = array(
				'page'	=> $filesDisplay[$file]['page'],
				'media'	=> $filesDisplay[$file]['media'],
				'time'	=> $filesDisplay[$file]['time'],
				'last'	=> 'N');
		}
	}
} // End foreach(glob('files/*.*') as $file)
// End of $filesDisplay building

// Now, deduplicate (find the latest of numerus media files succesively called by the same page)
$filesLastVersions = array(); // $page.'0µ0'.$mediaName => $time
if (array_key_exists(0, $filesVersions)) {
	unset($filesVersions[0]);
}
foreach ($filesVersions as $file => $fileVersion) {
	$compoundKey = $fileVersion['page'].'0µ0'.$fileVersion['media'];
	if (array_key_exists($compoundKey, $filesLastVersions)) { // Found a record for (page, media) key
		if ($filesLastVersions[$compoundKey] < $fileVersion['time']) {
			$filesLastVersions[$compoundKey] = $fileVersion['time'];
		}
	} else { // create a record for the (page, media) pair
		$filesLastVersions[$compoundKey] = $fileVersion['time'];
	}
} // End of foreach ($filesVersions as $file => $fileVersion)
// Now $filesLastVersions contains, for each page-media pair the last media version

// Set the media latest version
// Second part of $filesDisplay building
if (array_key_exists(0, $filesDisplay)) {
	unset($filesDisplay[0]);
}
foreach ($filesDisplay as $file => $fileDisplay) {
	$fileDisplay['latestMediaVersion'] = false;
	$latestMedia = false;

	// For media attached to latest version of active pages, set $filesVersions
	if (!($filesDisplay[$file]['bazar']) && ! is_null($filesDisplay[$file]['media'])) {
	// We have a real page (no bazar record and no problem)
		if ($filesDisplay[$file]['pageIsActive'] && $filesDisplay[$file]['pageIsLatest']) {
		// Media used on the latest version of an active page
			$fileDisplay['latestMediaVersion'] = true; // _t('MEDIA_LATEST_VERSION');
		}
	}
} // End foreach ($filesDisplay as $file => $fileDisplay)
// End of second part of $filesDisplay building

$deleteButton = '<form action="'.$this->href('',$this->tag).'" method="post">';
$deleteButton .= '<input type="hidden" name="media_delete" value="'.$file.'" />';
$deleteButton .= '<input class="btn btn-sm btn-danger" type="submit" value="'._t('MEDIA_DELETE').'" />';
$deleteButton .= $this->FormClose();

// Files table display
echo '<table class="table table-striped table-condensed table-hover" style="table-layout: fixed;">', "\n";
echo '<thead>', "\n";
echo '<tr>', "\n";
echo '	<th style="min-width: 25%;">'._t('MEDIA_FILE').'</th>', "\n";
echo '	<th style="min-width: 25%;">'._t('MEDIA_NAME').'</th>', "\n";
echo '	<th>'._t('MEDIA_UPLOAD_TIME').'</th>', "\n";
echo '	<th>'._t('MEDIA_PAGE').'</th>', "\n";
echo '	<th>'._t('MEDIA_PAGE_VERSION').'</th>', "\n";
echo '	<th>'._t('MEDIA_VERSION').'</th>', "\n";
echo '	<th> </th>', "\n";
echo '</tr>', "\n";
echo '</thead>', "\n";
echo '<tbody>', "\n";
foreach ($filesDisplay as $file => $fileDisplay) {
	echo '<tr>', "\n";
	if ($fileDisplay['bazar']) { // A bazar record
		echo '	<td colspan="3" style="min-width: 50%; white-space: normal !important; word-wrap: break-word;">'.$file.'</td>', "\n";
		echo '	<td colspan="3" style="white-space: normal !important; word-wrap: break-word;">'.$fileDisplay['page'].$fileDisplay['additionalPageText'].'</td>', "\n";
		echo '	<td> </td>', "\n"; // No delete button
	} elseif (is_null($fileDisplay['page'])){ // We have a problem
		echo '	<td colspan="6" style="min-width: 50%; white-space: normal !important; word-wrap: break-word;">'._t('MEDIA_UNABLE_TO_UNDERSTAND_FILE_NAME').'<br/>'.$file.'</td>', "\n";
		echo '	<td>'.$deleteButton.'</td>', "\n";
	} elseif (is_null($fileDisplay['media'])){ // Not found
		echo '	<td colspan="6" style="min-width: 50%; white-space: normal !important; word-wrap: break-word;">'._t('MEDIA_UNABLE_TO_UNDERSTAND_FILE_NAME').'<br/>'.$file.'</td>', "\n";
		echo '	<td>'.$deleteButton.'</td>', "\n";
	} else { // Real Wiki page
		// 1st col, MEDIA_FILE
		echo '	<td style="min-width: 25%; white-space: normal !important; word-wrap: break-word;">'.$file.'</td>', "\n";
		// 2nd col, MEDIA_NAME
		echo '	<td style="min-width: 25%; white-space: normal !important; word-wrap: break-word;">'.$fileDisplay['media'].'</td>', "\n";
		// 3rd col, MEDIA_UPLOAD_TIME
		$time = $fileDisplay['time'];
		echo '	<td>'.substr($time, 0, 4).'-'.substr($time, 4, 2).'-'.substr($time, 6, 2).' '.substr($time, 8, 2).':'.substr($time, 10, 2).':'.substr($time, 12, 2).'</td>', "\n";
		// 4th col, MEDIA_PAGE
		echo '	<td style="white-space: normal !important; word-wrap: break-word;">';
		echo $fileDisplay['page'].$fileDisplay['additionalPageText'];
		if (!$fileDisplay['pageIsActive']){
			echo '<br/>'.(_t('MEDIA_INACTIVE_PAGE'));
		}
		echo '</td>', "\n";
		// 5th col, MEDIA_PAGE_VERSION
		echo '	<td>';
		if ($fileDisplay['pageIsLatest']){
			echo (_t('MEDIA_USED_ON_PAGE_LATEST_VERSION')). '<br/>';
		} else {
			echo (_t('MEDIA_UNUSED_ON_PAGE_LATEST_VERSION')). '<br/>';
		}
		echo '</td>', "\n";
		// 6th col, MEDIA_VERSION
		echo '	<td>';
		if ($fileDisplay['latestMediaVersion']){
			echo (_t('MEDIA_LATEST_VERSION')). '<br/>';
		}
		echo '</td>', "\n";
		// 7th col, delete button
		echo '	<td>';
		if (!$fileDisplay['pageIsActive'] || !$fileDisplay['pageIsLatest'] || !$fileDisplay['MediaIsLatest']){
			echo $deleteButton;
		}
		echo '</td>', "\n";
	}
	echo '</tr>', "\n";
} // End Foreach
echo '</tbody>', "\n";
echo '</table>', "\n";
// End of files table display
?>
