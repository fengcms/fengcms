/* Build result URLs without emitting absent or malformed record identifiers. */
(function () {
    'use strict';
    function executionURL(controller, result, options = {}) {
        result = result || {};
        const params = new URLSearchParams({controller, operate:'execution', re:result.status === 'y' ? '1' : '0'});
        const type = options.type === undefined ? 'save' : options.type;
        if (type) params.set('type', type);
        const currentProject = ['module','fieldmanage','recover'].includes(controller)
            ? new URL(window.location.href).searchParams.get('project') : '';
        const project = options.project || result.project || currentProject;
        if (typeof project === 'string' && /^[a-zA-Z][a-zA-Z0-9_]*$/.test(project)) params.set('project', project);
        const id = result.id;
        if (options.includeId !== false && (typeof id === 'string' || typeof id === 'number') && /^[0-9]{1,10}$/.test(String(id)) && Number(id) > 0) {
            params.set('id', String(id));
        }
        return '?' + params.toString();
    }
    window.FengAdmin = {executionURL};
}());
