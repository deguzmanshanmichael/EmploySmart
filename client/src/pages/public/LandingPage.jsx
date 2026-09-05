import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiArrowRight, FiBriefcase, FiCheckCircle, FiCompass, FiHeart, FiMail, FiMapPin, FiPhone, FiSearch, FiUsers } from 'react-icons/fi'
import { settingsService } from '../../services/index'

const defaults = {
  municipality_name: 'EmploySmart Municipality',
  municipality_region: 'Philippines',
  contact_office: 'PESO',
  contact_email: 'peso@example.org',
  contact_phone: '',
  welcome_message: 'Welcome to the PESO employment portal.',
  landing_hero_title: 'Find work that moves your future forward.',
  landing_hero_subtitle: 'EmploySmart connects jobseekers, employers, PESO, and CLCDO through one trusted local employment platform.',
  landing_about: 'EmploySmart makes local employment services easier to discover, manage, and access. Search opportunities, build skills, connect with employers, and follow your progress in one place.',
  landing_mission: 'To connect people to decent work and practical skills through accessible, transparent, and community-centered employment services.',
  landing_vision: 'A thriving local workforce where every capable person can discover opportunity and every responsible employer can find the talent they need.',
  landing_peso: 'PESO supports job matching, employer coordination, job approval, applicant monitoring, and employment reports for the community.',
  landing_clcdo: 'CLCDO coordinates training programs, participant enrollment, skills development, and completion tracking for local residents.',
  landing_hero_image: '',
  landing_logo_image: '',
  landing_primary_color: '#047857',
  landing_accent_color: '#f59e0b',
  landing_primary_light_color: '#34d399',
  landing_primary_dark_color: '#064e3b',
  landing_dark_color: '#0f172a',
  landing_background_color: '#f4f8f5',
  landing_surface_color: '#ffffff',
  landing_text_color: '#1e293b',
  landing_muted_text_color: '#64748b',
  landing_border_color: '#e2e8f0',
  landing_footer_text: 'Connecting people, skills, and opportunity.',
}

function BrandMark() {
  return <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white shadow-lg shadow-emerald-900/20">ES</span>
}

export default function LandingPage({ loading = false }) {
  const [content, setContent] = useState(defaults)

  useEffect(() => {
    settingsService.getLanding()
      .then((response) => setContent({ ...defaults, ...(response.data?.data || {}) }))
      .catch(() => {})
  }, [])

  if (loading) return <div className="min-h-screen bg-[#f4f8f5]" />

  const themeStyle = {
    '--landing-primary': content.landing_primary_color,
    '--landing-accent': content.landing_accent_color,
    '--landing-primary-light': content.landing_primary_light_color,
    '--landing-primary-dark': content.landing_primary_dark_color,
    '--landing-dark': content.landing_dark_color,
    '--landing-background': content.landing_background_color,
    '--landing-surface': content.landing_surface_color,
    '--landing-text': content.landing_text_color,
    '--landing-muted': content.landing_muted_text_color,
    '--landing-border': content.landing_border_color,
  }

  return (
    <div style={themeStyle} className="landing-page min-h-screen overflow-hidden bg-[#f4f8f5] text-slate-800">
      <header className="absolute inset-x-0 top-0 z-20 border-b border-white/10 bg-slate-950/20 text-white backdrop-blur-md">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
          <a href="#top" className="flex items-center gap-3">{content.landing_logo_image ? <img src={content.landing_logo_image} alt="EmploySmart" className="h-10 w-10 rounded-xl object-cover" /> : <BrandMark />}<span className="font-display text-lg font-bold tracking-tight">EmploySmart</span></a>
          <nav className="hidden items-center gap-7 text-sm font-semibold text-white/80 md:flex">
            <a href="#about" className="transition hover:text-white">About</a>
            <a href="#services" className="transition hover:text-white">Services</a>
            <a href="#mission" className="transition hover:text-white">Our purpose</a>
            <a href="#contact" className="transition hover:text-white">Contact</a>
          </nav>
          <Link to="/login" className="btn rounded-full bg-white px-4 py-2 text-slate-900 shadow-lg hover:bg-emerald-50">Sign in <FiArrowRight /></Link>
        </div>
      </header>

      <main id="top">
        <section className="relative isolate min-h-[720px] overflow-hidden bg-slate-950 text-white">
          <div className="absolute inset-0" style={{ backgroundImage: content.landing_hero_image ? `linear-gradient(135deg, color-mix(in srgb, var(--landing-dark) 92%, transparent), color-mix(in srgb, var(--landing-primary-dark) 78%, transparent)), url(${content.landing_hero_image})` : 'linear-gradient(135deg, var(--landing-dark), var(--landing-primary-dark) 55%, var(--landing-primary))', backgroundSize: 'cover', backgroundPosition: 'center' }} />
          <div className="absolute -right-24 top-28 h-80 w-80 rounded-full border border-emerald-300/20" />
          <div className="absolute right-16 top-52 h-48 w-48 rounded-full border border-emerald-300/20" />
          <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-5 pb-20 pt-40 lg:grid-cols-[1.05fr_.95fr] lg:px-8 lg:pt-48">
            <div className="max-w-3xl">
              <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.18em] text-emerald-200"><FiCompass /> {content.municipality_name}</p>
              <h1 className="max-w-3xl font-display text-5xl font-bold leading-[1.02] tracking-tight sm:text-6xl">{content.landing_hero_title}</h1>
              <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-200">{content.landing_hero_subtitle}</p>
              <div className="mt-9 flex flex-wrap gap-3">
                <Link to="/register" className="btn btn-lg rounded-full bg-emerald-400 text-slate-950 hover:bg-emerald-300">Create an account <FiArrowRight /></Link>
                <Link to="/login/user" className="btn btn-lg rounded-full border border-white/30 bg-white/10 text-white hover:bg-white/20">Explore opportunities</Link>
              </div>
              <p className="mt-7 text-sm text-emerald-100/80">{content.welcome_message}</p>
            </div>
            <div className="relative mx-auto w-full max-w-md">
              <div className="rounded-[2rem] border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur-xl">
                <div className="mb-5 flex items-center justify-between border-b border-white/15 pb-4"><span className="text-sm font-semibold text-white/80">A clearer path to opportunity</span><span className="h-2.5 w-2.5 rounded-full bg-emerald-300" /></div>
                <div className="space-y-3">
                  {[['01', 'Build your profile', 'Show employers what you can do.'], ['02', 'Discover opportunities', 'Search jobs and training near you.'], ['03', 'Move forward', 'Apply, learn, and track progress.']].map(([number, title, text]) => <div key={number} className="flex gap-4 rounded-2xl bg-slate-950/25 p-4"><span className="font-display text-sm font-bold text-emerald-300">{number}</span><div><h2 className="font-semibold">{title}</h2><p className="mt-1 text-sm leading-6 text-slate-300">{text}</p></div></div>)}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="about" className="mx-auto max-w-7xl px-5 py-20 lg:px-8 lg:py-28">
          <div className="grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
            <div><p className="text-sm font-bold uppercase tracking-[0.18em] text-emerald-700">One local workforce network</p><h2 className="mt-3 font-display text-4xl font-bold tracking-tight text-slate-950">Employment support that feels connected.</h2></div>
            <div className="max-w-3xl"><p className="text-xl leading-9 text-slate-600">{content.landing_about}</p><div className="mt-8 grid gap-4 sm:grid-cols-3"><div className="border-l-2 border-emerald-500 pl-4"><FiSearch className="text-emerald-700" /><p className="mt-3 font-semibold">Find the right fit</p><p className="mt-1 text-sm text-slate-500">Search opportunities by role, skill, and location.</p></div><div className="border-l-2 border-amber-500 pl-4"><FiUsers className="text-amber-700" /><p className="mt-3 font-semibold">Meet local employers</p><p className="mt-1 text-sm text-slate-500">Connect with verified organizations hiring nearby.</p></div><div className="border-l-2 border-sky-500 pl-4"><FiCheckCircle className="text-sky-700" /><p className="mt-3 font-semibold">Keep growing</p><p className="mt-1 text-sm text-slate-500">Build skills through practical training programs.</p></div></div></div>
          </div>
        </section>

        <section id="services" className="border-y border-slate-200 bg-white"><div className="mx-auto max-w-7xl px-5 py-20 lg:px-8 lg:py-24"><div className="max-w-2xl"><p className="text-sm font-bold uppercase tracking-[0.18em] text-emerald-700">What EmploySmart does</p><h2 className="mt-3 font-display text-4xl font-bold tracking-tight text-slate-950">Designed around the people who make local opportunity possible.</h2></div><div className="mt-12 grid gap-5 md:grid-cols-3"><article className="rounded-2xl bg-slate-950 p-7 text-white"><FiBriefcase className="text-emerald-300" size={28} /><h3 className="mt-12 font-display text-2xl font-bold">For jobseekers</h3><p className="mt-3 leading-7 text-slate-300">Create a profile, discover matching jobs, apply with confidence, and develop skills through community training.</p><Link to="/register" className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-emerald-300">Start your journey <FiArrowRight /></Link></article><article className="rounded-2xl bg-emerald-50 p-7"><FiUsers className="text-emerald-700" size={28} /><h3 className="mt-12 font-display text-2xl font-bold text-slate-950">For employers</h3><p className="mt-3 leading-7 text-slate-600">Post openings, reach prepared candidates, review applications, and grow with a stronger local talent pool.</p><Link to="/register" className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-emerald-800">Find your next hire <FiArrowRight /></Link></article><article className="rounded-2xl bg-amber-50 p-7"><FiHeart className="text-amber-700" size={28} /><h3 className="mt-12 font-display text-2xl font-bold text-slate-950">For the community</h3><p className="mt-3 leading-7 text-slate-600">Coordinate employment programs, training, verification, reporting, and outcomes in one shared system.</p><a href="#mission" className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-amber-800">Learn about the mission <FiArrowRight /></a></article></div></div></section>

        <section id="mission" className="mx-auto max-w-7xl px-5 py-20 lg:px-8 lg:py-28"><div className="grid gap-6 lg:grid-cols-2"><article className="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 lg:p-10"><p className="text-sm font-bold uppercase tracking-[0.18em] text-emerald-700">Mission</p><p className="mt-6 font-display text-2xl font-semibold leading-9 text-slate-950">{content.landing_mission}</p></article><article className="rounded-2xl border border-slate-200 bg-slate-950 p-8 text-white lg:p-10"><p className="text-sm font-bold uppercase tracking-[0.18em] text-emerald-300">Vision</p><p className="mt-6 font-display text-2xl font-semibold leading-9 text-white">{content.landing_vision}</p></article></div><div className="mt-16 grid gap-10 lg:grid-cols-2"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-emerald-700">PESO</p><h2 className="mt-3 font-display text-3xl font-bold text-slate-950">Public Employment Service Office</h2><p className="mt-4 max-w-xl leading-8 text-slate-600">{content.landing_peso}</p></div><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-amber-700">CLCDO</p><h2 className="mt-3 font-display text-3xl font-bold text-slate-950">Community livelihood and development</h2><p className="mt-4 max-w-xl leading-8 text-slate-600">{content.landing_clcdo}</p></div></div></section>

        <section id="contact" className="bg-emerald-700 text-white"><div className="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-16 lg:flex-row lg:items-center lg:justify-between lg:px-8"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-emerald-200">Connect with your local office</p><h2 className="mt-3 font-display text-3xl font-bold">Ready to take the next step?</h2><p className="mt-3 max-w-xl text-emerald-50">Talk to {content.contact_office} about job matching, skills development, and employment support in {content.municipality_name}.</p></div><div className="space-y-3 text-sm text-emerald-50"><p className="flex items-center gap-3"><FiMapPin /> {content.municipality_name}, {content.municipality_region}</p><p className="flex items-center gap-3"><FiMail /> {content.contact_email}</p>{content.contact_phone && <p className="flex items-center gap-3"><FiPhone /> {content.contact_phone}</p>}</div></div></section>
      </main>

      <footer className="bg-slate-950 text-slate-400"><div className="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-7 text-sm sm:flex-row sm:items-center sm:justify-between lg:px-8"><div className="flex items-center gap-3"><BrandMark /><span>EmploySmart · {content.municipality_name}</span></div><p>{content.landing_footer_text}</p></div></footer>
    </div>
  )
}
