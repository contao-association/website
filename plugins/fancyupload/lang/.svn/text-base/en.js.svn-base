// Avoiding MooTools.lang dependency
(function() {
	var phrases = {
		'progressOverall': 'Overall Progress ({total})',
		'currentTitle': 'File Progress',
		'currentFile': 'Uploading "{name}"',
		'currentProgress': 'Upload: {bytesLoaded} with {rate}, {timeRemaining} remaining.',
		'fileName': '{name}',
		'remove': 'Remove',
		'removeTitle': 'Click to remove this entry.',
		'fileError': 'Upload failed',
		'validationErrors': {
			'duplicate': 'File <em>{name}</em> is already added, duplicates are not allowed.',
			'sizeLimitMin': 'File <em>{name}</em> (<em>{size}</em>) is too small, the minimal file size is {fileSizeMin}.',
			'sizeLimitMax': 'File <em>{name}</em> (<em>{size}</em>) is too big, the maximal file size is <em>{fileSizeMax}</em>.',
			'fileListMax': 'File <em>{name}</em> could not be added, amount of <em>{fileListMax} files</em> exceeded.',
			'fileListSizeMax': 'File <em>{name}</em> (<em>{size}</em>) is too big, overall filesize of <em>{fileListSizeMax}</em> exceeded.'
		},
		'errors': {
			'httpStatus': 'Server returned HTTP-Status <code>#{code}</code>',
			'securityError': 'Security error occured ({text})',
			'ioError': 'Error caused a send or load operation to fail ({text})'
		}
	};
	
	if (MooTools.lang) {
		MooTools.lang.set('en-US', 'FancyUpload', phrases);
	} else {
		MooTools.lang = {
			get: function(from, key) {
				return phrases[key];
			}
		};
	}
})();