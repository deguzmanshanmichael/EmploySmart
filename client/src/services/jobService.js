import api from './api'

export const jobService = {
  getAll: (params) => api.get('/jobs', { params }),
  getOne: (id) => api.get(`/jobs/${id}`),
  create: (data) => api.post('/jobs', data),
  update: (id, data) => api.put(`/jobs/${id}`, data),
  delete: (id) => api.delete(`/jobs/${id}`),
  approve: (id, status) => api.patch(`/jobs/${id}/approve`, { status }),
  getPending: (params) => api.get('/jobs/pending', { params }),
  getByEmployer: (employerId, params) => api.get(`/employers/${employerId}/jobs`, { params }),
  getApplicants: (jobId, params) => api.get(`/jobs/${jobId}/applicants`, { params }),
}