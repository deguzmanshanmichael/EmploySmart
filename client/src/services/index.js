import api from './api'

export const applicationService = {
  apply: (data) => api.post('/applications', data),
  getMyApplications: (params) => api.get('/applications/my', { params }),
  getJobApplicants: (jobId, params) => api.get(`/jobs/${jobId}/applicants`, { params }),
  updateStatus: (id, data) => api.patch(`/applications/${id}/status`, data),
  withdraw: (id) => api.delete(`/applications/${id}/withdraw`),
}

export const messageService = {
  getThread: (applicationId) => api.get(`/messages/${applicationId}`),
  sendMessage: (applicationId, data) => api.post(`/messages/${applicationId}`, data),
}

export const employerService = {
  getAll: (params) => api.get('/employers', { params }),
  getOne: (id) => api.get(`/employers/${id}`),
  getByUser: (userId) => api.get(`/employers/user/${userId}`),
  update: (id, data) => api.put(`/employers/${id}`, data),
  verify: (id, status) => api.patch(`/employers/${id}/verify`, { status }),
  getAnalytics: (id) => api.get(`/employers/${id}/analytics`),
  uploadDocument: (id, formData) =>
    api.post(`/employers/${id}/document`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
}

export const trainingService = {
  getAll: (params) => api.get('/training', { params }),
  getOne: (id) => api.get(`/training/${id}`),
  create: (data) => api.post('/training', data),
  update: (id, data) => api.put(`/training/${id}`, data),
  delete: (id) => api.delete(`/training/${id}`),
  enroll: (trainingId, userId) => api.post(`/training/${trainingId}/enroll`, { user_id: userId }),
  complete: (trainingId, userId, data) =>
    api.post(`/training/${trainingId}/complete/${userId}`, data),
  getUserTrainings: (userId) => api.get(`/training/user/${userId}`),
}

export const notificationService = {
  getAll: () => api.get('/notifications'),
  create: (data) => api.post('/notifications', data),
  markRead: (id) => api.patch(`/notifications/${id}/read`),
  markAllRead: () => api.patch('/notifications/read-all'),
}

export const matchService = {
  getRecommended: () => api.get('/matches/recommended'),
  getScore: (userId, jobId) => api.get(`/matches/${userId}/${jobId}`),
  runMatch: (userId) => api.post(`/matches/${userId}`),
}

export const skillService = {
  getAll: () => api.get('/skills'),
  create: (data) => api.post('/skills', data),
  delete: (id) => api.delete(`/skills/${id}`),
  getUserSkills: (userId) => api.get(`/skills/user/${userId}`),
  getProgression: (userId) => api.get(`/skills/progression/${userId}`),
  updateUserSkills: (userId, skills) => api.put(`/skills/user/${userId}`, { skills }),
}

export const settingsService = {
  getLanding: () => api.get('/settings/landing'),
  getMunicipality: () => api.get('/settings/municipality'),
  updateMunicipality: (data) => api.post('/settings/municipality', data),
  resetMunicipality: () => api.post('/settings/municipality/reset'),
}

export const feedbackService = {
  create: (data) => api.post('/feedback', data),
  getForTarget: (targetType, targetId) => api.get(`/feedback/${targetType}/${targetId}`),
}

export const resumeRecommendationService = {
  getForUser: (userId) => api.get(`/resume-recommendations/${userId}`),
}

export const userService = {
  getAll: (params) => api.get('/users', { params }),
  getOne: (id) => api.get(`/users/${id}`),
  update: (id, data) => api.put(`/users/${id}`, data),
  updatePassword: (id, data) => api.patch(`/users/${id}/password`, data),
  verify: (id) => api.patch(`/users/${id}/verify`),
  delete: (id) => api.delete(`/users/${id}`),
  getDashboardStats: () => api.get('/users/stats'),
  uploadResume: (id, formData) =>
    api.post(`/users/${id}/resume`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  generateResume: (id) =>
    api.get(`/users/${id}/resume`, { responseType: 'blob' }),
  uploadAvatar: (id, formData) =>
    api.post(`/users/${id}/avatar`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
}