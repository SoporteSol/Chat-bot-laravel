import 'bootstrap';
import axios from 'axios';

// Cliente HTTP para peticiones al backend con header AJAX
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

