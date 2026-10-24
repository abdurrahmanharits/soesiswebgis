/**
 * Page entry point. Each Blade page declares <main data-page="...">;
 * page modules are loaded lazily so later pages (e.g. the OpenLayers map)
 * don't add weight to the dataset screens.
 */
const pages = {
    'datasets.index': () => import('./datasets/list.js').then((m) => m.initDatasetList()),
    'datasets.show': (root) => import('./datasets/detail.js').then((m) => m.initDatasetDetail(root)),
    'map.index': () => import('./map/page.js').then((m) => m.initMapPage()),
    'catalogue.index': (root) => import('./catalogue/list.js').then((m) => m.initCatalogueList(root)),
    'catalogue.show': (root) => import('./catalogue/detail.js').then((m) => m.initCatalogueDetail(root)),
    'catalogue.map': () => import('./map/page.js').then((m) => m.initMapPage({ source: 'catalogue' })),
};

const root = document.querySelector('[data-page]');

pages[root?.dataset.page]?.(root);
