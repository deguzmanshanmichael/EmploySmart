import api from './api'

export const authService = {
  login: (credentials) => api.post('/auth/login', credentials),
  register: (data) => api.post('/auth/register', data),
  logout: () => {
    const refreshToken = localStorage.getItem('refresh_token')
    return api.post('/auth/logout', { refresh_token: refreshToken })
  },
  refresh: (refreshToken) => api.post('/auth/refresh', { refresh_token: refreshToken }),
  me: () => api.get('/auth/me'),
}