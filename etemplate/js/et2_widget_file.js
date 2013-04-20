/**
 * EGroupware eTemplate2 - JS Number object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_inputWidget;
	phpgwapi.jquery.jquery.html5_upload;
*/

/**
 * Class which implements file upload
 * 
 * @augments et2_inputWidget
 */ 
var et2_file = et2_inputWidget.extend(
{
	attributes: {
		"multiple": {
			"name": "Multiple files",
			"type": "boolean",
			"default": false,
			"description": "Allow the user to select more than one file to upload at a time.  Subject to browser support."
		},
		"max_file_size": {
			"name": "Maximum file size",
			"type": "integer",
			"default":"8388608",
			"description": "Largest file accepted, in bytes.  Subject to server limitations.  8Mb = 8388608"
		},
		"mime": {
			"name": "Allowed file types",
			"type": "string",
			"default": et2_no_init,
			"description": "Mime type (eg: image/png) or regex (eg: /^text\//i) for allowed file types"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": "",
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
		"progress": {
			"name": "Progress node",
			"type": "string",
			"default": et2_no_init,
			"description": "The ID of an alternate node (div) to display progress and results."
		},
		"onStart": {
			"name": "Start event handler",
			"type": "any",
			"default": et2_no_init,
			"description": "A (js) function called when an upload starts.  Return true to continue with upload, false to cancel."
		},
		"onFinish": {
			"name": "Finish event handler",
			"type": "any",
			"default": et2_no_init,
			"description": "A (js) function called when all files to be uploaded are finished."
		}
	},

	asyncOptions: {},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_file
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.node = null;
		this.input = null;
		this.progress = null;

		if(!this.options.id) {
			console.warn("File widget needs an ID.  Used 'file_widget'.");
			this.options.id = "file_widget";
		}

		// Legacy - id ending in [] means multiple
		if(this.options.id.substr(-2) == "[]")
		{
			this.options.multiple = true;
		}

		// Set up the URL to have the request ID & the widget ID
		var instance = this.getInstanceManager();
	
		var self = this;

		this.asyncOptions = jQuery.extend({
			// Callbacks
			onStart: function(event, file_count) { 
				// Hide any previous errors
				self.hideMessage(); 
				return self.onStart(event, file_count); 
			},
			onFinish: function(event, file_count) { 
				self.onFinish(event, file_count);

				// Fire legacy change action when done
				self.change(self.input);
			},
			onStartOne: function(event, file_name, index, file_count) { 
				// Here 'this' is the input
				if(self.checkMime(this.files[index]))
				{
					return self.createStatus(event,file_name, index,file_count);
				}
				else
				{
					// Wrong mime type - show in the list of files
					return self.createStatus(
						self.egw().lang("File is of wrong type (%1 != %2)!", this.files[index].type, self.options.mime),
						file_name
					);
				}
			},
			onFinishOne: function(event, response, name, number, total) { return self.finishUpload(event,response,name,number,total);},
			onProgress: function(event, progress, name, number, total) { return self.onProgress(event,progress,name,number,total);},
			onError: function(event, name, error) { return self.onError(event,name,error);},
			sendBoundary: window.FormData || jQuery.browser.mozilla,
			beforeSend: function(form) { return self.beforeSend(form);},
			url: egw_json_request.prototype._assembleAjaxUrl("etemplate_widget_file::ajax_upload::etemplate"),
			autoclear: !(this.options.onchange)
		},this.asyncOptions);
		this.asyncOptions.fieldName = this.options.id;
		this.createInputWidget();
	},

	destroy: function() {
		this.node = null;
		this.input = null;
		this.progress = null;
	},
	
	createInputWidget: function() {
		this.node = $j(document.createElement("div")).addClass("et2_file");
		this.input = $j(document.createElement("input"))
			.attr("type", "file").attr("placeholder", this.options.blur)
			.appendTo(this.node);

		// Check for File interface, should fall back to normal form submit if missing
		if(typeof File != "undefined" && typeof (new XMLHttpRequest()).upload != "undefined")
		{
			this.input.html5_upload(this.asyncOptions);
		}
		else
		{
			// This may be a problem submitting via ajax
		}
		this.progress = this.options.progress ? 
			$j(document.getElementById(this.options.progress)) : 
			$j(document.createElement("div")).appendTo(this.node);
		this.progress.addClass("progress");

		if(this.options.multiple)
		{
			this.input.attr("multiple","multiple");
		}

		this.setDOMNode(this.node[0]);

	},
	attachToDOM: function() {
		this._super.apply(this, arguments);
		// Override parent's behaviour to fire legacy change when finished
		if (this.onchange)
		{
			this.input.unbind("change.et2_inputWidget");
		}
	},
	getValue: function() {
		var value = this.options.value ? this.options.value : this.input.val();
		return value;
	},
	
	getInputNode: function() {
		return this.input[0];
	},


	set_mime: function(mime) {
		if(!mime)
		{
			this.options.mime = null;
		}
		if(mime.indexOf("/") != 0)
		{
			// Lower case it now, if it's not a regex
			this.options.mime = mime.toLowerCase();
		}
		else
		{
			// Convert into a js regex
			var parts = mime.substr(1).match(/(.*)\/([igm]?)$/);
			this.options.mime = new RegExp(parts[1],parts.length > 2 ? parts[2] : "");
		}
	},

	set_multiple: function(_multiple) {
		this.options.multiple = _multiple;
		if(_multiple)
		{
			return this.input.attr("multiple", "multiple");
		}
		return this.input.removeAttr("multiple");
	},
	/**
	 * Check to see if the provided file's mimetype matches
	 *
	 * @param f File object
	 * @return boolean
	 */
	checkMime: function(f) {
		// If missing, let the server handle it
		if(!this.options.mime || !f.type) return true;

		var is_preg = (typeof this.options.mime == "object");
		if(!is_preg && f.type.toLowerCase() == this.options.mime || is_preg && this.options.mime.test(f.type))
		{
			return true;
		}

		// Not right mime
		return false;
	},

	/**
	 * Add in the request id
	 */
	beforeSend: function(form) {
		var instance = this.getInstanceManager();
		// Form object available, mess with it directly
		if(form) {
			return form.append("request_id", instance.etemplate_exec_id);
		}

		// No Form object, add in as string
		return 'Content-Disposition: form-data; name="request_id"\r\n\r\n'+instance.etemplate_exec_id+'\r\n';
	},

	/**
	 * Disables submit buttons while uploading
	 */
	onStart: function(event, file_count) {
		this.disabled_buttons = $j("input[type='submit'], button").not("[disabled]").attr("disabled", true);

		event.data = this;
		if(this.options.onStart) return et2_call(this.options.onStart, event, file_count);
		return true;
	},

	/**
	 * Re-enables submit buttons when done
	 */
	onFinish: function(event, file_count) {
		this.disabled_buttons.attr("disabled", false);
		event.data = this;
		if(this.options.onFinish) return et2_call(this.options.onFinish, event, file_count);
	},

	
	/**
	 * Creates the elements used for displaying the file, and it's upload status, and
	 * attaches them to the DOM
	 *
	 * @param _event Either the event, or an error message
	 */
	createStatus: function(_event, file_name, index, file_count) {
		var error = (typeof _event == "object" ? "" : _event);
		if(this.input[0].files[index]) {
			var file = this.input[0].files[index];
			if(file.size > this.options.max_file_size) {
				error = this.egw().lang("File too large.  Maximum %1", et2_vfsSize.prototype.human_size(this.options.max_file_size));
			}
		}
		if(this.progress)
		{
			var status = $j("<li file='"+file_name+"'>"+file_name
					+"<div class='remove'/><span class='progressBar'><p/></span></li>")
				.appendTo(this.progress);
			$j("div.remove",status).click(this, this.cancel);
			if(error != "")
			{
				status.addClass("message validation_error");
				status.append("<div>"+error+"</diff>");
				$j(".progressBar",status).css("display", "none");
			}
		}
		return error == "";
	},

	onProgress: function(event, percent, name, number, total) {
		if(this.progress)
		{
			$j("li[file='"+name+"'] > span.progressBar > p").css("width", Math.ceil(percent*100)+"%");

		}
		return true;
	},

	onError: function(event, name, error) {
console.warn(event,name,error);
	},

	/**
	 * A file upload is finished, update the UI
	 */
	finishUpload: function(event, response, name, number, total) {
		if(typeof response == 'string') response = jQuery.parseJSON(response);
		if(response.response[0] && typeof response.response[0].data.length == 'undefined') {
			if(typeof this.options.value != 'object') this.options.value = {};
			for(var key in response.response[0].data) {
				if(typeof response.response[0].data[key] == "string")
				{
					// Message from server - probably error
					$j("[file='"+name+"']",this.progress)
						.addClass("error")
						.css("display", "block")
						.text(response.response[0].data[key]);
				}
				else
				{
					this.options.value[key] = response.response[0].data[key];
					if(this.progress)
					{
						$j("[file='"+name+"']",this.progress).addClass("message success");
					}
				}
			}
		}
		else if (this.progress)
		{
			$j("[file='"+name+"']",this.progress)
				.addClass("error")
				.css("display", "block")
				.text(this.egw().lang("Server error"));
		}
		if(this.options.onFinishOne)
		{
			return et2_call(this.options.onFinishOne,event,response,name,number,total);
		}
		return true;
	},

	/**
	 * Cancel a file
	 */
	cancel: function(e) {
		e.preventDefault();
		// Look for file name in list
		var target = $j(e.target).parents("li.message");
console.info(target);
		for(var key in e.data.options.value) {
			if(e.data.options.value[key].name == target.attr("file"))
			{
				delete e.data.options.value[key];
				target.remove();
				return;
			}
		}
		// In case it didn't make it to the list (error)
		target.remove();
		$j(e.target).remove();
	}
});

et2_register_widget(et2_file, ["file"]);

