function supernetworkPostNew( selector )
{
	var extra = selector.value === currentId ? '' : '&blog_id=' + selector.value;
	document.querySelector( '.page-title-action' ).attributes.href.value = originalURL + extra;
}

var newHTML = '<select style="margin-top: -10px;" onchange="supernetworkPostNew(this);">';

for ( var id in blogs )
{
	selected = id === currentId ? ' selected="selected"' : '';
	current = id === currentId ? ' (current site)' : '';
	newHTML += '<option value="' + id + '"' + selected + '>' + blogs[ id ] + current + '</option>';
}

newHTML += '</select>';

var pageTitleAction = document.querySelector( '.page-title-action' );
var originalURL = pageTitleAction.attributes.href.value;
pageTitleAction.outerHTML = newHTML + pageTitleAction.outerHTML;
