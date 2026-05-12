import { startStimulusApp } from '@symfony/stimulus-bundle';
import AdminFormMaskController from './controllers/admin_form_mask_controller.js';

const app = startStimulusApp();
app.register('admin-form-mask', AdminFormMaskController);
