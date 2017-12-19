	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_logfile">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="92">
						&nbsp;Tail Lines:&nbsp;
					</td>
					<td width="1">
						<select name="tail_lines" onChange="applyViewLogFilterChange(document.form_logfile)">
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if ($_REQUEST["tail_lines"] == $tail_lines) { print " selected"; } print ">" . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="100">
						&nbsp;Message Type:&nbsp;
					</td>
					<td width="1">
						<select name="message_type" onChange="applyViewLogFilterChange(document.form_logfile)">
							<option value="-1"<?php if ($_REQUEST['message_type'] == '-1') {?> selected<?php }?>>All</option>
							<option value="1"<?php if ($_REQUEST['message_type'] == '1') {?> selected<?php }?>>Stats</option>
							<option value="2"<?php if ($_REQUEST['message_type'] == '2') {?> selected<?php }?>>Warnings</option>
							<option value="3"<?php if ($_REQUEST['message_type'] == '3') {?> selected<?php }?>>Errors</option>
							<option value="4"<?php if ($_REQUEST['message_type'] == '4') {?> selected<?php }?>>Debug</option>
							<option value="5"<?php if ($_REQUEST['message_type'] == '5') {?> selected<?php }?>>SQL Calls</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input class="button_go" type="submit" name="go" value="Go" alt="Go" border="0" align="absmiddle">
						<input class="button_clear" type="submit" name="clear" value="Clear" alt="Clear" border="0" align="absmiddle">
						<?php if (clog_admin()) {?><input class="button_purge" type="submit" name="purge" value="Purge" alt="Purge" border="0" align="absmiddle"><?php }?>
					</td>
				</tr>
				<tr>
					<td nowrap style='white-space: nowrap;' width="92">
						&nbsp;Refresh:&nbsp;
					</td>
					<td width="1">
						<select name="refresh" onChange="applyViewLogFilterChange(document.form_logfile)">
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if ($_REQUEST["refresh"] == $seconds) { print " selected"; } print ">" . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="100">
						&nbsp;Display Order:&nbsp;
					</td>
					<td width="1">
						<select name="reverse" onChange="applyViewLogFilterChange(document.form_logfile)">
							<option value="1"<?php if ($_REQUEST['reverse'] == '1') {?> selected<?php }?>>Newest First</option>
							<option value="2"<?php if ($_REQUEST['reverse'] == '2') {?> selected<?php }?>>Oldest First</option>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="92">
						&nbsp;Search/Regex:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="75" value="<?php print $_REQUEST["filter"];?>">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
