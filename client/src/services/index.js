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
  approveEnrollment: (trainingId, userId) => api.patch(`/training/${trainingId}/approve/${userId}`),
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
  addCustom: (userId, skillName) => api.post(`/skills/user/${userId}/custom`, { skill_name: skillName }),
}

export const settingsService = {
  getLanding: () => api.get('/settings/landing'),
  getStorageStatus: () => api.get('/settings/storage'),
  getMunicipality: () => api.get('/settings/municipality'),
  updateMunicipality: (data) => api.post('/settings/municipality', data),
  resetMunicipality: () => api.post('/settings/municipality/reset'),
  uploadLandingImage: (imageType, file) => {
    const formData = new FormData()
    formData.append('image_type', imageType)
    formData.append('image', file)
    return api.post('/settings/municipality/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
}

export const feedbackService = {
  create: (data) => api.post('/feedback', data),
  getForTarget: (targetType, targetId) => api.get(`/feedback/${targetType}/${targetId}`),
  getPlacements: (params) => api.get('/feedback/placements', { params }),
  getPlacement: (applicationId) => api.get(`/feedback/placements/${applicationId}`),
  createForPlacement: (applicationId, data) => api.post(`/feedback/placements/${applicationId}`, data),
  reply: (threadId, message) => api.post(`/feedback/thread/${threadId}/replies`, { message }),
  setStatus: (threadId, status) => api.patch(`/feedback/thread/${threadId}/status`, { status }),
  getSummary: () => api.get('/feedback/summary'),
}

export const skillGapService = {
  getOverview: () => api.get('/skill-gaps', { timeout: 60000 }),
  getApplicant: (userId) => api.get(`/skill-gaps/user/${userId}`),
  getMine: () => api.get('/skill-gaps/user/me'),
  recommendTraining: (userId, trainingId, message) => api.post(`/skill-gaps/user/${userId}/recommend`, { training_id: trainingId, message }),
}

export const resumeRecommendationService = {
  getForUser: (userId, params) => api.get(`/resume-recommendations/${userId}`, { params }),
  getMine: (params) => api.get('/resume-recommendations/me', { params }),
}

// Resume parsing can take longer than ordinary requests (large PDFs, optional AI assistance).
const RESUME_TIMEOUT = 180000

export const resumeProfileService = {
  get: () => api.get('/resume-profile'),
  upload: (file, onUploadProgress) => {
    const formData = new FormData()
    formData.append('resume', file)
    return api.post('/resume-profile/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: RESUME_TIMEOUT,
      onUploadProgress,
    })
  },
  submitOcrText: (text) => api.post('/resume-profile/ocr', { text }, { timeout: RESUME_TIMEOUT }),
  confirm: (data) => api.post('/resume-profile/confirm', data, { timeout: 60000 }),
  remove: (removeSkills = false) => api.delete('/resume-profile', { params: removeSkills ? { remove_skills: 1 } : {} }),
  downloadFile: (userId) => api.get(`/users/${userId}/uploaded-resume`, { responseType: 'blob', timeout: 60000 }),
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