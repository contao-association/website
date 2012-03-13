// Avoiding MooTools.lang dependency
(function() {
	var phrases = {
		'progressOverall': 'Gesamtfortschritt ({total})',
		'currentTitle': 'Dateifortschritt',
		'currentFile': 'Lade "{name}"',
		'currentProgress': 'Laden: {bytesLoaded} mit {rate}, {timeRemaining} verbleibend.',
		'fileName': '{name}',
		'remove': 'Entfernen',
		'removeTitle': 'Klicken um Datei zu entfernen.',
		'fileError': 'Fehlgeschlagen',
		'validationErrors': {
			'duplicate': 'Datei <em>{name}</em> wurde bereits hinzugefügt, Duplikate sind nicht erlaubt.',
			'sizeLimitMin': 'Datei <em>{name}</em> (<em>{size}</em>) ist zu klein, die minimale Grösse beträgt {fileSizeMin}.',
			'sizeLimitMax': 'Datei <em>{name}</em> (<em>{size}</em>) is zu groß, die maximale Grösse beträgt <em>{fileSizeMax}</em>.',
			'fileListMax': 'Datei <em>{name}</em> could not be added, amount of <em>{fileListMax} files</em> exceeded.',
			'fileListSizeMax': 'Datei <em>{name}</em> (<em>{size}</em>) ist zu groß, Gesamtgrösse von <em>{fileListSizeMax}</em> überschritten.'
		},
		'errors': {
			'httpStatus': 'Server antwortet HTTP-Status <code>#{code}</code>',
			'securityError': 'Sicherheitsfehler aufgetreten ({text})',
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