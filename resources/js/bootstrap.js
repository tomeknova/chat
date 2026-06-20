import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Bootstrap 5 JS (Modal, Dropdown, Collapse...) — same as KINGS5.
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
