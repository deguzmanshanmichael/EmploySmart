/**
 * matchUtils.js
 * Client-side helpers for displaying and interpreting job match scores.
 * Actual scoring computation is done server-side by JobMatchingService.php
 */

/**
 * Returns a label and color class for a given match score (0–100)
 */
export function getMatchLabel(score) {
  if (score >= 85) return { label: 'Excellent Match',  color: 'text-green-600',  bg: 'bg-green-50',  bar: 'bg-green-500' }
  if (score >= 65) return { label: 'Good Match',       color: 'text-blue-600',   bg: 'bg-blue-50',   bar: 'bg-blue-500' }
  if (score >= 45) return { label: 'Fair Match',       color: 'text-yellow-600', bg: 'bg-yellow-50', bar: 'bg-yellow-500' }
  if (score >= 25) return { label: 'Partial Match',    color: 'text-orange-600', bg: 'bg-orange-50', bar: 'bg-orange-400' }
  return           { label: 'Low Match',              color: 'text-red-500',    bg: 'bg-red-50',    bar: 'bg-red-400' }
}

/**
 * Returns a short emoji indicator for a score
 */
export function getMatchEmoji(score) {
  if (score >= 85) return '🟢'
  if (score >= 65) return '🔵'
  if (score >= 45) return '🟡'
  if (score >= 25) return '🟠'
  return '🔴'
}

/**
 * Sorts an array of jobs by match_score descending.
 */
export function sortByMatchScore(jobs) {
  return [...jobs].sort((a, b) => (b.match_score ?? 0) - (a.match_score ?? 0))
}

/**
 * Filters jobs to only those with a match score above a threshold.
 */
export function filterByMinScore(jobs, minScore = 30) {
  return jobs.filter(j => (j.match_score ?? 0) >= minScore)
}

/**
 * Returns which skills from a user's list match the job's required skills.
 * @param {number[]} userSkillIds
 * @param {number[]} jobSkillIds
 * @returns {{ matched: number[], missing: number[] }}
 */
export function getSkillGap(userSkillIds, jobSkillIds) {
  const userSet = new Set(userSkillIds)
  const matched = jobSkillIds.filter(id => userSet.has(id))
  const missing = jobSkillIds.filter(id => !userSet.has(id))
  return { matched, missing }
}

/**
 * Returns a % string for the match score bar width (clamped 0–100)
 */
export function scoreToWidth(score) {
  return `${Math.min(100, Math.max(0, Math.round(score)))}%`
}